<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Screen;

use Magento\Backend\Model\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Shipping\Model\Shipment\Draft\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Screen\ScreenDataProvider;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\PointsMap;
use Polkurier\Webservice\Response\CourierPoint;

#[CoversClass(ScreenDataProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class ScreenDataProviderTest extends TestCase
{
    public function testAnOpenPointComesBackAsPolkurierHasItNow(): void
    {
        $point = new CourierPoint('WAW195M', available: true, address: '02-134 Warszawa, 1 Sierpnia 36B');

        self::assertSame($point, $this->context($point)->findRecipientPoint('INPOST_PACZKOMAT', 'WAW195M'));
    }

    public function testAPointGoneOrClosedIsSaidToBe(): void
    {
        self::assertFalse(
            $this->context(null)->findRecipientPoint('INPOST_PACZKOMAT', 'WAW299X'),
            'No longer there.',
        );
        self::assertFalse(
            $this->context(new CourierPoint('WAW195M', available: false))
                ->findRecipientPoint('INPOST_PACZKOMAT', 'WAW195M'),
            'Closed.',
        );
    }

    public function testWhatCantBeToldIsNeverTakenAsClosed(): void
    {
        self::assertNull(
            $this->context(new ConnectionException('cURL error 7'))
                ->findRecipientPoint('INPOST_PACZKOMAT', 'WAW195M'),
            'Polkurier unreachable.',
        );
        self::assertNull($this->context(null)->findRecipientPoint('INPOST_PACZKOMAT', ''), 'No point.');
        self::assertNull(
            $this->context(null, carrier: false)->findRecipientPoint('GONE', 'WAW195M'),
            'No such carrier.',
        );
    }

    public function testThePointIsDescribedByItsNameAndAddressTodayOrAsTheCustomerChoseIt(): void
    {
        $open = new CourierPoint(
            'WAW195M',
            name: 'Paczkomat przy sklepie',
            available: true,
            address: '02-134 Warszawa, 1 Sierpnia 36B',
        );

        self::assertSame(
            [
                'id' => 'WAW195M',
                'name' => 'Paczkomat przy sklepie',
                'label' => '02-134 Warszawa, 1 Sierpnia 36B',
                'closed' => false,
            ],
            $this->context($open)->describeRecipientPoint(42, 'INPOST_PACZKOMAT', 'WAW195M'),
        );
        self::assertSame(
            ['id' => 'WAW195M', 'name' => '', 'label' => 'Paczkomat WAW195M, as chosen', 'closed' => true],
            $this->context(null)->describeRecipientPoint(42, 'INPOST_PACZKOMAT', 'WAW195M'),
            'Gone since: told by the label the customer chose it by.',
        );
        self::assertSame(
            ['id' => 'WAW299X', 'name' => '', 'label' => '', 'closed' => true],
            $this->context(null)->describeRecipientPoint(42, 'INPOST_PACZKOMAT', 'WAW299X'),
            'Another point than the customer\'s has no label of theirs.',
        );
    }

    public function testThePointsAScreenStartsWithAreDescribedByCode(): void
    {
        $points = [
            'KRA01M' => new CourierPoint('KRA01M', description: 'Pickup - Shop', address: '31-401 Kraków, Długa 1'),
            'POZ02M' => new CourierPoint('POZ02M', name: 'Kiosk', available: false, address: '60-001 Poznań, Polna 2'),
        ];
        $recipient = ['id' => 'WAW195M', 'name' => '', 'label' => 'Paczkomat WAW195M, as chosen'];

        self::assertSame(
            [
                'KRA01M' => ['name' => 'Pickup - Shop', 'label' => '31-401 Kraków, Długa 1'],
                'POZ02M' => ['name' => 'Kiosk', 'label' => '60-001 Poznań, Polna 2'],
                'WAW195M' => ['name' => '', 'label' => 'Paczkomat WAW195M, as chosen'],
            ],
            $this->context($points, senderPoints: ['POZ02M', '', 'GDA03M'])
                ->describePoints($recipient, 'KRA01M'),
            'The drop-off point and the senders\' points, closed or not; those not found are left out.',
        );
        self::assertSame(
            [],
            $this->context($points)->describePoints(['id' => 'WAW299X', 'name' => '', 'label' => ''], ''),
            'A pickup point with nothing to tell is left out.',
        );
    }

    /**
     * @param CourierPoint|\Throwable|array<string, CourierPoint>|null $found what Polkurier has, by
     *     code for more than one point
     * @param string[] $senderPoints the drop-off points of the sender addresses
     */
    private function context(
        CourierPoint|\Throwable|array|null $found,
        bool $carrier = true,
        array $senderPoints = [],
    ): ScreenDataProvider {
        $paczkomat = new Carrier('INPOST_PACZKOMAT', 'Paczkomat', false, new AdditionalData(
            pointsMap: new PointsMap(['inpost'], recipientPoint: PointRequirementEnum::Required),
        ));
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')->willReturn($carrier ? $paczkomat : null);
        $catalog->method('getCarriers')->willReturn([
            'INPOST_PACZKOMAT' => $paczkomat,
            'DPD_POINT' => new Carrier('DPD_POINT', 'DPD Pickup', false, new AdditionalData(
                pointsMap: new PointsMap(['dpd'], recipientPoint: PointRequirementEnum::Required),
            )),
        ]);
        if ($found instanceof \Throwable) {
            $catalog->method('findPoint')->willThrowException($found);
        } elseif (\is_array($found)) {
            // Found only among every carrier's providers.
            $catalog->method('findPoint')->willReturnCallback(
                static fn(string $pointId, array $providers): ?CourierPoint => \count($providers) === 2
                    ? $found[$pointId] ?? null
                    : null,
            );
        } else {
            $catalog->method('findPoint')->willReturn($found);
        }
        $addresses = $this->createStub(AddressRepositoryInterface::class);
        $addresses->method('getSenderAddresses')->willReturn(\array_map(function (string $pointId): AddressInterface {
            $address = $this->createStub(AddressInterface::class);
            $address->method('getPointId')->willReturn($pointId);

            return $address;
        }, $senderPoints));

        $chosen = $this->createStub(OrderPointInterface::class);
        $chosen->method('getPointId')->willReturn('WAW195M');
        $chosen->method('getPointLabel')->willReturn('Paczkomat WAW195M, as chosen');
        $points = $this->createStub(OrderPointRepositoryInterface::class);
        $points->method('getForOrder')->willReturn($chosen);

        return new ScreenDataProvider(
            $this->createStub(DraftHydrator::class),
            $this->createStub(PackTemplateRepositoryInterface::class),
            $addresses,
            $this->createStub(PackType::class),
            $this->createStub(MapTokenProvider::class),
            $this->createStub(ConfigProvider::class),
            $this->createStub(OrderAmountCalculator::class),
            $catalog,
            new CarrierFeatureResolver(),
            $this->createStub(DefaultDraftBuilder::class),
            $points,
            $this->createStub(UrlInterface::class),
        );
    }
}
