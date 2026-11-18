<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Controller\Adminhtml\Bankaccount\SendCode as BankAccountSendCode;
use Polkurier\Shipping\Controller\Adminhtml\Carrier\Arrange;
use Polkurier\Shipping\Controller\Adminhtml\Carrier\UploadLogo;
use Polkurier\Shipping\Controller\Adminhtml\Payout\Quote as PayoutQuote;
use Polkurier\Shipping\Controller\Adminhtml\Payout\SendCode as PayoutSendCode;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Calculate;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\CollectivePickup;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\PickupTimes;
use Polkurier\Shipping\Model\Admin\Formatter;
use Polkurier\Shipping\Model\BankAccount\Publisher;
use Polkurier\Shipping\Model\Carrier\CarrierLogoStorage;
use Polkurier\Shipping\Model\CarrierConfig\CarrierSortOrderUpdater;
use Polkurier\Shipping\Model\Payout\MoneyBoxOrderFinder;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\PickupService;
use Polkurier\Shipping\Model\Shipment\Placement\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Shipment\Valuation\CostEstimator;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;
use Polkurier\Webservice\Dto\Response\MoneyBox\MoneyBoxFeeMessage;
use Polkurier\Webservice\Dto\Response\TwoFactorToken;
use Polkurier\Webservice\Entity\Order\PackItem;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;
use Polkurier\Webservice\Exception\ConnectionException;

/**
 * The admin actions that answer the screens with JSON: prices, collection times, a collective
 * pickup's availability, the carriers' order, a carrier's logo, the codes a confirmation needs and
 * a payout's fee.
 */
#[CoversClass(Calculate::class)]
#[CoversClass(PickupTimes::class)]
#[CoversClass(CollectivePickup::class)]
#[CoversClass(Arrange::class)]
#[CoversClass(UploadLogo::class)]
#[CoversClass(BankAccountSendCode::class)]
#[CoversClass(PayoutSendCode::class)]
#[CoversClass(PayoutQuote::class)]
#[AllowMockObjectsWithoutExpectations]
class JsonActionsTest extends AdminActionTestCase
{
    public function testTheChosenCarriersPriceComesWithEveryCarriersQuote(): void
    {
        $this->params = ['data' => ['order_id' => 7]];
        $quotes = ['DPD' => ['net' => 10.0, 'gross' => 12.3], 'GLS' => ['net' => 9.0, 'gross' => 11.07]];

        $this->createCalculate('DPD', $quotes)->execute();

        self::assertFalse($this->json['error'] ?? null);
        self::assertEquals((object)$quotes, $this->json['quotes'] ?? null);
    }

    public function testACarrierThatDoesntPriceTheShipmentIsSaidToBeUnavailable(): void
    {
        $this->createCalculate('INPOST', ['DPD' => ['net' => 10.0, 'gross' => 12.3]])->execute();

        self::assertTrue($this->json['error'] ?? null);
        self::assertSame('Service unavailable', $this->json['message'] ?? null);
    }

    public function testAPriceThatCantBeAskedForOffersRetryOnlyWhenItMayHelp(): void
    {
        $this->createCalculate('DPD', new LocalizedException(
            __('Polkurier is not answering.'),
            new ConnectionException('cURL error 7: Failed to connect'),
        ))->execute();
        $unreachable = $this->json;
        $this->createCalculate('DPD', new LocalizedException(__('No such service.')))->execute();

        self::assertSame([true, 'Polkurier is not answering.', true], [
            $unreachable['error'] ?? null,
            $unreachable['message'] ?? null,
            $unreachable['retry'] ?? null,
        ]);
        self::assertFalse($this->json['retry'] ?? null);
    }

    public function testCollectionTimesNeedACarrier(): void
    {
        $pickups = $this->createMock(PickupService::class);
        $pickups->expects(self::never())->method('getPickupTimes');

        $this->createPickupTimes($pickups)->execute();

        self::assertSame(
            ['error' => true, 'message' => 'Choose a carrier first.', 'retry' => false, 'days' => []],
            $this->json,
        );
    }

    public function testCollectionTimesAreAskedForTheShipmentsTypeOrABox(): void
    {
        $this->params = ['data' => [
            ShipmentDraftInterface::FIELD_CARRIER_CODE => 'DPD',
            ShipmentDraftInterface::FIELD_RECIPIENT_POSTCODE => '31-147',
        ]];
        $days = [['date' => '2026-11-18', 'windows' => [['from' => '10:00', 'to' => '14:00']]]];
        $pickups = $this->createMock(PickupService::class);
        $pickups->method('getSenderPostcode')->willReturn('00-838');
        $pickups->expects(self::once())->method('getPickupTimes')
            ->with('DPD', '00-838', '31-147', 'box')
            ->willReturn($days);

        $this->createPickupTimes($pickups)->execute();

        self::assertSame(['error' => false, 'days' => $days], $this->json);
    }

    public function testACollectivePickupIsntAskedAboutWithoutACarrierOrParcels(): void
    {
        $pickups = $this->createMock(PickupService::class);
        $pickups->expects(self::never())->method('isCollectiveAvailable');

        $this->params = ['data' => [ShipmentDraftInterface::FIELD_CARRIER_CODE => 'DPD']];
        $this->createCollectivePickup($pickups, [])->execute();

        self::assertSame(['error' => false, 'available' => null], $this->json);
    }

    public function testACollectivePickupIsAskedAboutForTheParcels(): void
    {
        $this->params = ['data' => [
            ShipmentDraftInterface::FIELD_CARRIER_CODE => 'DPD',
            ShipmentDraftInterface::FIELD_SHIPMENT_TYPE => 'palette',
        ]];
        $pickups = $this->createMock(PickupService::class);
        $pickups->method('getSenderPostcode')->willReturn('00-838');
        $pickups->expects(self::once())->method('isCollectiveAvailable')
            ->with('DPD', '00-838', 'palette', self::countOf(2))
            ->willReturn(false);

        $this->createCollectivePickup($pickups, [new Pack(), new Pack()])->execute();

        self::assertSame(['error' => false, 'available' => false], $this->json);
    }

    public function testCarriersAreArrangedByTheIdsSentLeavingOutWhatIsntOne(): void
    {
        $this->params = ['ids' => ['3', 'x', '1', '0', '2']];
        $updater = $this->createMock(CarrierSortOrderUpdater::class);
        $updater->expects(self::once())->method('arrange')->with([3, 1, 2]);

        (new Arrange($this->createContext(), $this->createJsonFactory(), $updater))->execute();

        self::assertSame(['error' => false], $this->json);
    }

    public function testAnUploadedLogoIsTakenFromTheFieldTheFormNames(): void
    {
        $this->params = ['param_name' => 'logo_dark'];
        $uploaded = [
            'file' => 'dpd.png',
            'name' => 'dpd.png',
            'url' => 'https://shop.example/dpd.png',
            'size' => 10,
            'type' => 'image/png',
        ];
        $storage = $this->createMock(CarrierLogoStorage::class);
        $storage->expects(self::once())->method('upload')->with('logo_dark')->willReturn($uploaded);

        (new UploadLogo($this->createContext(), $this->createJsonFactory(), $storage))->execute();

        self::assertSame($uploaded, $this->json);
    }

    public function testARefusedLogoAnswersAsTheUploaderExpects(): void
    {
        $storage = $this->createStub(CarrierLogoStorage::class);
        $storage->method('upload')->willThrowException(new LocalizedException(__('Not an image.')));

        (new UploadLogo($this->createContext(), $this->createJsonFactory(), $storage))->execute();

        self::assertSame(['error' => 'Not an image.', 'errorcode' => 1], $this->json);
    }

    public function testACodeSentForABankAccountOrAPayoutSaysWhereTo(): void
    {
        $token = new TwoFactorToken('tok-1', '+48 600 *** 200', 'Payout', 1, null, null, null);
        $publisher = $this->createStub(Publisher::class);
        $publisher->method('requestCode')->willReturn($token);
        $payouts = $this->createStub(PayoutManager::class);
        $payouts->method('requestCode')->willReturn($token);
        $expected = ['error' => false, 'token' => 'tok-1', 'message' => 'Code sent to +48 600 *** 200.'];

        (new BankAccountSendCode($this->createContext(), $this->createJsonFactory(), $publisher))->execute();
        $forBankAccount = $this->json;
        (new PayoutSendCode($this->createContext(), $this->createJsonFactory(), $payouts))->execute();

        self::assertSame([$expected, $expected], [$forBankAccount, $this->json]);
    }

    public function testAPayoutsFeeIsQuotedForTheOrdersTypedInAndNamesThoseTheShopDoesntKnow(): void
    {
        $this->params = ['data' => [
            'orders' => "24680-27, 24680-28\n24680-27",
            'payout_type' => MoneyBoxPayoutTypeEnum::Account->value,
        ]];
        $payouts = $this->createMock(PayoutManager::class);
        $payouts->expects(self::once())->method('quote')
            ->with(['24680-27', '24680-28'], MoneyBoxPayoutTypeEnum::Account)
            ->willReturn(new MoneyBoxFeeMessage(fee: 2.46, feeNet: 2.0, freePayoutsLeft: 1, freePayoutsTotal: 3));
        $payouts->method('getAvailableBalance')->willReturn(150.0);
        $finder = $this->createStub(MoneyBoxOrderFinder::class);
        $finder->method('findUnknown')->willReturn(['24680-28']);
        $formatter = $this->createStub(Formatter::class);
        $formatter->method('formatMoney')
            ->willReturnCallback(static fn(float $amount): string => \number_format($amount, 2) . ' PLN');

        (new PayoutQuote($this->createContext(), $this->createJsonFactory(), $payouts, $finder, $formatter))->execute();

        self::assertSame(
            [
                'error' => false,
                'available' => 150.0,
                'unknown' => ['24680-28'],
                'fee' => 2.46,
                'fee_net' => 2.0,
                'free_left' => 1,
                'free_total' => 3,
                'message' => 'Fee: 2.46 PLN gross (2.00 PLN net). Free payouts left: 1 of 3.',
            ],
            $this->json,
        );
    }

    /**
     * @param array<string, array{net: float, gross: float}>|LocalizedException $quotes
     */
    private function createCalculate(string $carrierCode, array|LocalizedException $quotes): Calculate
    {
        $draft = $this->createStub(ShipmentDraftInterface::class);
        $draft->method('getCarrierCode')->willReturn($carrierCode);
        $hydrator = $this->createStub(DraftHydrator::class);
        $hydrator->method('createFromArray')->willReturn($draft);
        $orders = $this->createStub(OrderLoader::class);
        $orders->method('getForPolkurier')->willReturn($this->createStub(Order::class));
        $estimator = $this->createStub(CostEstimator::class);
        $quotes instanceof LocalizedException
            ? $estimator->method('quoteAll')->willThrowException($quotes)
            : $estimator->method('quoteAll')->willReturn($quotes);

        return new Calculate(
            $this->createContext(),
            $this->createJsonFactory(),
            $estimator,
            $hydrator,
            $orders,
            new ErrorMessageResolver(),
        );
    }

    private function createPickupTimes(PickupService $pickupService): PickupTimes
    {
        return new PickupTimes(
            $this->createContext(),
            $this->createJsonFactory(),
            $pickupService,
            new ErrorMessageResolver(),
        );
    }

    /**
     * @param Pack[] $packs the parcels the draft is read with
     */
    private function createCollectivePickup(PickupService $pickupService, array $packs): CollectivePickup
    {
        $draft = $this->createStub(ShipmentDraftInterface::class);
        $draft->method('getPacks')->willReturn($packs);
        $hydrator = $this->createStub(DraftHydrator::class);
        $hydrator->method('createFromArray')->willReturn($draft);
        $mapper = $this->createStub(CreateOrderMessageMapper::class);
        $mapper->method('mapPack')->willReturn($this->createStub(PackItem::class));

        return new CollectivePickup(
            $this->createContext(),
            $this->createJsonFactory(),
            $pickupService,
            $hydrator,
            $mapper,
        );
    }
}
