<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Screen;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Shipment\Placement\SenderMapper;
use Polkurier\Shipping\Model\Shipment\Screen\ShipmentOptionsProvider;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Request\Order\OrderAddressMessage;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Serializer\SerializerProvider;

#[CoversClass(ShipmentOptionsProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentOptionsProviderTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $logged = [];

    public function testAnUnreadableCarrierListSaysWhyOnceLogged(): void
    {
        $options = $this->options(new ConnectionException('Timed out.'));

        self::assertSame([], $options->getCarriers());
        self::assertSame([], $options->getCarrierFeatures());
        self::assertSame(
            'Polkurier could not be reached, or did not answer in time: Timed out.',
            $options->getCarriersError(),
        );
        self::assertCount(1, $this->logged);
    }

    public function testAReadListHasNoError(): void
    {
        $options = $this->options(null);

        self::assertSame(['DPD'], \array_keys($options->getCarriers()));
        self::assertNull($options->getCarriersError());
        self::assertSame([], $this->logged);
    }

    public function testCarriersAreOfferedByNameOrByCodeWithoutOne(): void
    {
        $options = $this->options(null, [...$this->fixtureCarriers(), 'UPS' => new Carrier('UPS', '', false)]);

        self::assertSame([
            ['value' => 'INPOST_PACZKOMAT', 'label' => 'InPost Paczkomat'],
            ['value' => 'DPD', 'label' => 'DPD Classic'],
            ['value' => 'UPS', 'label' => 'UPS'],
        ], $options->getCarrierOptions());
    }

    public function testEachCarrierSaysWhatItSupports(): void
    {
        $features = $this->options(null, $this->fixtureCarriers())->getCarrierFeatures();

        self::assertSame(['INPOST_PACZKOMAT', 'DPD'], \array_keys($features));
        $always = [
            'SMS_NOTIFICATION_RECIPIENT',
            'SMS_NOTIFICATION_RECIPIENT_WITH_NAME',
            'SMS_NOTIFICATION_RECIPIENT_ORDER_ISSUED_FOR_DELIVERY',
            'SMS_NOTIFICATION_RECIPIENT_ORDER_ISSUED_FOR_DELIVERY_WITH_NAME',
            'CHECK_CONTENT',
        ];
        self::assertSame([
            'name' => 'DPD Classic',
            'services' => [...$always, 'ROD'],
            'point' => 'NONE',
            'providers' => [],
            'sender_point' => 'NONE',
            'cod' => true,
            'insurance' => true,
            'insurance_with_cod' => false,
            'drop_off' => false,
            'pickup_date' => true,
            'pickup_date_required' => true,
            'pickup_time_required' => true,
            'shipment_types' => ['envelope', 'box'],
            'fields' => [
                [
                    'name' => 'external_transport_security',
                    'label' => 'Zabezpieczenie',
                    'description' => '',
                    'required' => false,
                ],
            ],
        ], $features['DPD']);

        $inpost = $features['INPOST_PACZKOMAT'];
        self::assertSame([...$always, 'WEEK_COLLECTION'], $inpost['services']);
        self::assertSame(
            ['REQUIRED', ['INPOST'], 'OPTIONAL'],
            [$inpost['point'], $inpost['providers'], $inpost['sender_point']],
        );
        self::assertTrue($inpost['drop_off']);
        self::assertTrue($inpost['insurance_with_cod']);
        self::assertFalse($inpost['pickup_date']);
        self::assertSame(['box'], $inpost['shipment_types']);
        self::assertSame([], $inpost['fields']);
    }

    public function testSenderAddressesAreOfferedByTheirLabelsTheCoverOnesAlone(): void
    {
        $addresses = $this->createStub(AddressRepositoryInterface::class);
        $addresses->method('getSenderAddresses')->willReturnCallback(
            fn(bool $coverOnly = false): array => $coverOnly
                ? [$this->address(3, 'Magazyn Północ', 'Składowa', '3', null, '61-897', 'Poznań')]
                : [
                    $this->address(2, 'Biuro', 'Prosta', '20', '5', '00-838', 'Warszawa'),
                    $this->address(3, 'Magazyn Północ', 'Składowa', '3', null, '61-897', 'Poznań'),
                ],
        );
        $options = $this->options(null, addresses: $addresses);

        self::assertSame([
            ['value' => '2', 'label' => 'Biuro — Prosta 20/5, 00-838 Warszawa'],
            ['value' => '3', 'label' => 'Magazyn Północ — Składowa 3, 61-897 Poznań'],
        ], $options->getSenderOptions());
        self::assertSame(
            [['value' => '3', 'label' => 'Magazyn Północ — Składowa 3, 61-897 Poznań']],
            $options->getSenderOptions(true),
        );
    }

    public function testASenderIsTheStoresAddressOnlyWhenTheStoreHasOne(): void
    {
        $addresses = $this->createStub(AddressRepositoryInterface::class);
        $addresses->method('getSenderAddresses')->willReturn([
            $this->address(2, 'Biuro', 'Prosta', '20', '5', '00-838', 'Warszawa'),
        ]);
        $senders = $this->createStub(SenderMapper::class);
        $senders->method('fromStore')->willReturnCallback(
            static fn(int $storeId): ?OrderAddressMessage => $storeId === 1
                ? new OrderAddressMessage(
                    'Sklep',
                    'Prosta',
                    'Warszawa',
                    '00-838',
                    'sklep@example.com',
                    '600100200',
                    houseNumber: '20',
                )
                : null,
        );
        $options = $this->options(null, addresses: $addresses, senders: $senders);

        $address = ['value' => '2', 'label' => 'Biuro — Prosta 20/5, 00-838 Warszawa'];
        $new = ['value' => ShipmentDraftInterface::NEW, 'label' => 'A new address'];
        self::assertSame(
            [['value' => '', 'label' => 'The store\'s address'], $address, $new],
            $options->getSenderChoices(1),
        );
        self::assertSame([$address, $new], $options->getSenderChoices(2));
    }

    public function testCashOnDeliveryIsPaidOutToABankAccountOrANewOne(): void
    {
        $account = $this->createStub(BankAccountInterface::class);
        $account->method('getId')->willReturn(7);
        $account->method('getName')->willReturn('Główne');
        $account->method('getNumber')->willReturn('61109010140000071219812874');
        $bankAccounts = $this->createStub(BankAccountRepositoryInterface::class);
        $bankAccounts->method('getAvailable')->willReturn([$account]);
        $options = $this->options(null, bankAccounts: $bankAccounts);

        self::assertSame(
            [['value' => '7', 'label' => 'Główne — 61109010140000071219812874']],
            $options->getBankAccountOptions(),
        );
        self::assertSame([
            ['value' => '7', 'label' => 'Główne — 61109010140000071219812874'],
            ['value' => ShipmentDraftInterface::NEW, 'label' => 'A new bank account'],
        ], $options->getBankAccountChoices());
    }

    public function testAnAddressLabelNamesAFlatOnlyWhenThereIsOne(): void
    {
        $options = $this->options(null);

        self::assertSame(
            'Biuro — Prosta 20/5, 00-838 Warszawa',
            $options->addressLabel($this->address(2, 'Biuro', 'Prosta', '20', '5', '00-838', 'Warszawa')),
        );
        self::assertSame(
            'Magazyn — Składowa 3, 61-897 Poznań',
            $options->addressLabel($this->address(3, 'Magazyn', 'Składowa', '3', '', '61-897', 'Poznań')),
        );
    }

    public function testAFailureIsKeptForTheRequestAndForgottenAfterIt(): void
    {
        // Only the first read fails.
        $reads = 0;
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarriers')->willReturnCallback(static function () use (&$reads): array {
            return $reads++ === 0
                ? throw new ConnectionException('Timed out.')
                : ['DPD' => new Carrier('DPD', 'DPD', false)];
        });
        $options = $this->options(null, catalog: $catalog);

        self::assertNotNull($options->getCarriersError());
        self::assertNotNull($options->getCarriersError(), 'The page already told of the failure.');

        $options->_resetState();

        self::assertNull($options->getCarriersError());
        self::assertCount(1, $this->logged);
    }

    /**
     * The carriers of the recorded available_carriers answer, as the SDK reads them.
     *
     * @return array<string, Carrier>
     */
    private function fixtureCarriers(): array
    {
        $serializer = (new SerializerProvider())->getSerializer();
        $answer = (array)\json_decode(
            (string)\file_get_contents(__DIR__ . '/../../../_files/available_carriers.json'),
            true,
        );
        $carriers = [];
        foreach ((array)$answer['response'] as $data) {
            $carrier = $serializer->deserialize((string)\json_encode($data), Carrier::class, 'json');
            self::assertInstanceOf(Carrier::class, $carrier);
            $carriers[(string)$data['servicecode']] = $carrier;
        }

        return $carriers;
    }

    private function address(
        int $id,
        string $name,
        string $street,
        string $house,
        ?string $flat,
        string $postcode,
        string $city,
    ): AddressInterface {
        $address = $this->createStub(AddressInterface::class);
        $address->method('getId')->willReturn($id);
        $address->method('getName')->willReturn($name);
        $address->method('getStreet')->willReturn($street);
        $address->method('getHouseNumber')->willReturn($house);
        $address->method('getFlatNumber')->willReturn($flat);
        $address->method('getPostcode')->willReturn($postcode);
        $address->method('getCity')->willReturn($city);

        return $address;
    }

    /**
     * @param array<string, Carrier>|null $carriers what the catalog has; DPD alone by default
     */
    private function options(
        ?\Throwable $failure,
        ?array $carriers = null,
        ?AddressRepositoryInterface $addresses = null,
        ?BankAccountRepositoryInterface $bankAccounts = null,
        ?SenderMapper $senders = null,
        ?CarrierCatalog $catalog = null,
    ): ShipmentOptionsProvider {
        if ($catalog === null) {
            $catalog = $this->createStub(CarrierCatalog::class);
            $catalog->method('getCarriers')->willReturnCallback(static function () use ($failure, $carriers): array {
                if ($failure !== null) {
                    throw $failure;
                }

                return $carriers ?? ['DPD' => new Carrier('DPD', 'DPD', false)];
            });
        }
        $logger = $this->createStub(Logger::class);
        $logger->method('warning')->willReturnCallback(function (string $message): void {
            $this->logged[] = $message;
        });

        return new ShipmentOptionsProvider(
            $catalog,
            new CarrierFeatureResolver(),
            $addresses ?? $this->createStub(AddressRepositoryInterface::class),
            $bankAccounts ?? $this->createStub(BankAccountRepositoryInterface::class),
            new ErrorMessageResolver(),
            $logger,
            $senders ?? $this->createStub(SenderMapper::class),
        );
    }
}
