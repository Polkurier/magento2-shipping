<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Placement;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Placement\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Shipping\Model\Shipment\Placement\SenderMapper;
use Polkurier\Shipping\Model\Shipment\Placement\StreetParser;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\SdkSerializer;
use Polkurier\Webservice\Builder\Entity\Order\AddressBuilderFactory;
use Polkurier\Webservice\Builder\Entity\Order\CodBuilderFactory;
use Polkurier\Webservice\Builder\Entity\Order\PackItemBuilderFactory;
use Polkurier\Webservice\Builder\Entity\Order\PickupBuilderFactory;
use Polkurier\Webservice\Builder\Entity\OrderBuilderFactory;
use Polkurier\Webservice\Dto\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Serializer\SerializerConfig;
use Polkurier\Webservice\Validator\DefaultValidatorRegistryFactory;

#[CoversClass(CreateOrderMessageMapper::class)]
#[CoversClass(SenderMapper::class)]
class CreateOrderMessageMapperTest extends TestCase
{
    public function testMapsAFullDraftToTheWirePayload(): void
    {
        $draft = $this->createDraft()
            ->setSenderAddressId(7)
            ->setCodAmount(249.9)
            ->setCodType('1D')
            ->setCodReturnType('BA')
            ->setCodBankAccountId(3)
            ->setInsuranceAmount(250)
            ->setCoverAddressId(8)
            ->setServices(['SMS_NOTIFICATION_RECIPIENT', 'NOT_A_SERVICE', 'SMS_NOTIFICATION_RECIPIENT'])
            ->setPickupMode(ShipmentDraftInterface::PICKUP_COURIER)
            ->setPickupDate('2026-10-01')
            ->setPickupTimeFrom('10:00')
            ->setPickupTimeTo('14:30')
            ->setAdditionalFields(['external_transport_security' => 'YES', 'empty' => '']);

        $message = $this->createMapper()->map($draft, 1);
        DefaultValidatorRegistryFactory::create()->validate($message);
        $payload = $this->readPayload($message);

        self::assertSame('DPD', $payload['courier']);
        self::assertSame('box', $payload['shipmenttype']);
        self::assertSame('Zam. 000000042', $payload['description']);
        self::assertEquals(
            [['length' => 30, 'width' => 20, 'height' => 10, 'weight' => 2.5, 'type' => 'ST', 'amount' => 2]],
            $payload['packs'],
        );
        self::assertSame('Magazyn Sp. z o.o.', $payload['sender']['company']);
        self::assertSame('Fabryczna', $payload['sender']['street']);
        self::assertSame('7', $payload['sender']['housenumber']);
        self::assertSame('Anna Nowak', $payload['recipient']['person']);
        self::assertSame('5', $payload['recipient']['housenumber']);
        self::assertSame('3', $payload['recipient']['flatnumber']);
        self::assertSame(
            [
                'codamount' => 249.9,
                'codbankaccount' => '61109010140000071219812874',
                'codtype' => '1D',
                'return_cod' => 'BA',
            ],
            $this->sortValues($payload['COD']),
        );
        self::assertSame(250.0, $payload['insurance']);
        self::assertSame(
            ['Zwroty', '1'],
            [$payload['cover_address']['street'], $payload['cover_address']['housenumber']],
            'The cover address carries its house number as the others do.',
        );
        self::assertSame(['SMS_NOTIFICATION_RECIPIENT' => true], $payload['courierservice']);
        self::assertSame('2026-10-01', $payload['pickup']['pickupdate']);
        self::assertSame('10:00', $payload['pickup']['pickuptimefrom']);
        self::assertSame('14:30', $payload['pickup']['pickuptimeto']);
        self::assertSame(['external_transport_security' => 'YES'], $payload['additional_fields']);
    }

    public function testANamedSmsNotificationReplacesItsPlainVariant(): void
    {
        $payload = $this->readPayload($this->createMapper()->map($this->createDraft()->setServices([
            'SMS_NOTIFICATION_RECIPIENT',
            'SMS_NOTIFICATION_RECIPIENT_WITH_NAME',
            'SMS_NOTIFICATION_RECIPIENT_ORDER_ISSUED_FOR_DELIVERY',
        ]), 1));

        self::assertSame([
            'SMS_NOTIFICATION_RECIPIENT_WITH_NAME' => true,
            'SMS_NOTIFICATION_RECIPIENT_ORDER_ISSUED_FOR_DELIVERY' => true,
        ], $payload['courierservice'], 'Dispatch and out-for-delivery notifications are ordered independently.');
    }

    public function testMinimalDraftOmitsWhatWasNotAskedFor(): void
    {
        $payload = $this->readPayload($this->createMapper()->map($this->createDraft(), 1));

        $absentKeys = [
            'COD',
            'insurance',
            'cover_address',
            'courierservice',
            'additional_fields',
            'pickup',
            'external_application_id',
            'external_order_id',
            'external_order_number',
        ];
        foreach ($absentKeys as $absent) {
            self::assertArrayNotHasKey($absent, $payload);
        }
        self::assertSame('Warszawa', $payload['sender']['city'], 'No sender chosen: the store origin.');
        self::assertSame('sklep@example.com', $payload['sender']['email']);
    }

    public function testTheShopsIdsAreSentWithTheOrder(): void
    {
        $message = $this->createMapper()->map($this->createDraft(), 1, [
            'application' => 'magento2-0123456789ab',
            'id' => '000000077',
            'number' => '000000042',
        ]);
        DefaultValidatorRegistryFactory::create()->validate($message);
        $payload = $this->readPayload($message);

        self::assertSame('magento2-0123456789ab', $payload['external_application_id']);
        self::assertSame('000000077', $payload['external_order_id']);
        self::assertSame('000000042', $payload['external_order_number']);
    }

    public function testPointDeliveryAndDropOff(): void
    {
        $draft = $this->createDraft()
            ->setCarrierCode('INPOST_PACZKOMAT')
            ->setRecipientStreet('')
            ->setRecipientHouseNumber(null)
            ->setRecipientPointId('WAW01A')
            ->setPickupMode(ShipmentDraftInterface::PICKUP_DROP_OFF);

        $message = $this->createMapper()->map($draft, 1);
        DefaultValidatorRegistryFactory::create()->validate($message);
        $payload = $this->readPayload($message);

        self::assertSame('WAW01A', $payload['recipient']['point_id']);
        self::assertTrue((bool)$payload['pickup']['nocourierorder']);
    }

    public function testDescriptionIsCutToWhatTheApiKeeps(): void
    {
        $message = $this->createMapper()->map($this->createDraft()->setDescription(\str_repeat('x', 40)), 1);

        self::assertSame(Order::DESCRIPTION_MAX_LENGTH, \mb_strlen((string)$message->order->getDescription()));
    }

    public function testCashOnDeliveryToABankAccountNeedsOne(): void
    {
        $this->expectException(LocalizedException::class);

        $this->createMapper()->map(
            $this->createDraft()->setCodAmount(10)->setCodReturnType('BA')->setCodBankAccountId(null),
            1,
        );
    }

    public function testASenderTypedInGoesAsItWasTyped(): void
    {
        $draft = $this->createDraft()->setNewSender(true)->setNewSenderAddress([
            'person' => 'Ewa Lis',
            'company' => '',
            'street' => 'Polna',
            'house_number' => '3',
            'flat_number' => '2',
            'postcode' => '30-001',
            'city' => 'Kraków',
            'country' => '',
            'phone' => '501000111',
            'email' => 'ewa@example.com',
        ]);

        // Even with the store's address to fall back on.
        $sender = $this->createMapper()->map($draft, 1)->order->getSender();

        self::assertSame(['Ewa Lis', 'Polna', '3', '2', '30-001', 'Kraków', 'PL', null], [
            $sender->getPerson(), $sender->getStreet(), $sender->getHouseNumber(), $sender->getFlatNumber(),
            $sender->getPostcode(), $sender->getCity(), $sender->getCountry(), $sender->getCompany(),
        ]);
    }

    public function testASenderTypedInSaysWhatItLacks(): void
    {
        $draft = $this->createDraft()->setNewSender(true)->setNewSenderAddress([
            'person' => 'Ewa Lis',
            'street' => 'Polna',
            'city' => 'Kraków',
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Fill in the sender\'s house number, postcode, phone, e-mail.');

        $this->createMapper()->map($draft, 1);
    }

    public function testCashOnDeliveryToAnAccountTypedInCarriesItsNumber(): void
    {
        $draft = $this->createDraft()->setCodAmount(10)->setCodReturnType('BA')->setCodBankAccountId(null)
            ->setNewBankAccount(true)->setNewBankAccountNumber('61 1090 1014 0000 0712 1981 2874');

        self::assertSame(
            '61109010140000071219812874',
            $this->createMapper()->map($draft, 1)->order->getCod()?->getBankAccount(),
        );

        $this->expectException(LocalizedException::class);
        $this->createMapper()->map($draft->setNewBankAccountNumber(' '), 1);
    }

    public function testTheCollectionWindowGoesAsPolishTimeWhateverPhpsZone(): void
    {
        // Magento runs PHP in UTC; the module's serializer writes dates in Polish time.
        $zone = \date_default_timezone_get();
        \date_default_timezone_set('UTC');
        try {
            $message = $this->createMapper()->map(
                $this->createDraft()
                    ->setPickupDate('2026-10-05')
                    ->setPickupTimeFrom('14:00')
                    ->setPickupTimeTo('17:00'),
                1,
            );
            $pickup = (array)\json_decode(
                SdkSerializer::create(new SerializerConfig(timezone: new \DateTimeZone('Europe/Warsaw')))
                    ->encode($message),
                true,
            )['pickup'];
        } finally {
            \date_default_timezone_set($zone);
        }

        self::assertSame(
            ['2026-10-05', '14:00', '17:00'],
            [$pickup['pickupdate'], $pickup['pickuptimefrom'], $pickup['pickuptimeto']],
        );
    }

    public function testTheShipmentsDropOffPointGoesWithTheSender(): void
    {
        $withPoint = $this->createMapper()
            ->map($this->createDroppedOffDraft()->setSenderAddressId(7)->setSenderPointId('U16880214'), 1)
            ->order->getSender();
        $fromStore = $this->createMapper()->map(
            $this->createDroppedOffDraft()->setSenderPointId('U16880214'),
            1,
        )->order->getSender();
        $without = $this->createMapper()
            ->map($this->createDroppedOffDraft()->setSenderAddressId(7), 1)
            ->order->getSender();

        self::assertSame(
            ['U16880214', 'U16880214', null],
            [$withPoint->getPointId(), $fromStore->getPointId(), $without->getPointId()],
        );
        self::assertSame('Fabryczna', $withPoint->getStreet());
    }

    public function testParcelsHandedOverAtAPointBookNoCourier(): void
    {
        $pickup = $this->readPayload(
            $this->createMapper()->map($this->createDroppedOffDraft()->setSenderPointId('U16880214'), 1),
        )['pickup'];

        self::assertTrue($pickup['nocourierorder']);
        self::assertArrayNotHasKey('pickupdate', \array_filter($pickup, static fn($v) => $v !== null));
    }

    public function testAPointLeftInTheDraftIsNotSentWhenACourierCollects(): void
    {
        $courier = $this->createMapper()->map(
            $this->createDraft()->setSenderAddressId(7)->setSenderPointId('U16880214')->setPickupDate('2026-10-01'),
            1,
        )->order;
        $collective = $this->createMapper()->map(
            $this->createDraft()->setSenderPointId('U16880214')->setPickupMode(ShipmentDraftInterface::PICKUP_MULTI),
            1,
        )->order;

        self::assertSame([null, null], [$courier->getSender()->getPointId(), $collective->getSender()->getPointId()]);
        self::assertSame(
            [false, '2026-10-01'],
            [$courier->getPickup()?->isNoCourierOrder(), $courier->getPickup()?->getPickupDate()?->format('Y-m-d')],
        );
        self::assertSame(
            [false, true],
            [$collective->getPickup()?->isNoCourierOrder(), $collective->getPickup()?->isMultiPickup()],
        );
    }

    public function testParcelsDroppedOffWithoutAPointBookNoCourier(): void
    {
        $message = $this->createMapper()->map($this->createDroppedOffDraft(), 1);

        self::assertTrue($message->order->getPickup()?->isNoCourierOrder());
        self::assertNull($message->order->getSender()->getPointId());
    }

    public function testTheCoverAddressSwitchesTheServiceOnBySendingItself(): void
    {
        $payload = $this->readPayload($this->createMapper()->map(
            $this->createDraft()->setCoverAddressId(8)->setServices(
                ['COVER_ADDRESS_SENDER', 'SMS_NOTIFICATION_RECIPIENT'],
            ),
            1,
        ));

        self::assertSame('Zwroty', $payload['cover_address']['street']);
        self::assertSame(['SMS_NOTIFICATION_RECIPIENT' => true], $payload['courierservice']);
    }

    public function testTheCoverAddressSendsItsHouseAndFlatNumbersInTheirOwnFields(): void
    {
        $payload = $this->readPayload($this->createMapper()->map($this->createDraft()->setCoverAddressId(8), 1));

        self::assertSame(
            ['street' => 'Zwroty', 'housenumber' => '1', 'flat_number' => '3'],
            \array_intersect_key($payload['cover_address'], ['street' => 0, 'housenumber' => 0, 'flat_number' => 0]),
        );
    }

    public function testTheCoverSenderAddressWithoutAnAddressIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->createMapper()->map(
            $this->createDraft()->setCoverAddressId(null)->setServices(['COVER_ADDRESS_SENDER']),
            1,
        );
    }

    public function testTheShipmentTypeIsWhatTheParcelsAre(): void
    {
        $envelope = (new Pack())
            ->setPackType(ParcelTypeMapper::ENVELOPE)
            ->setLength(35)
            ->setWidth(25)
            ->setHeight(2)
            ->setWeight(0.5)
            ->setAmount(1);

        $message = $this->createMapper()->map($this->createDraft()->setShipmentType('box')->setPacks([$envelope]), 1);

        self::assertSame(ShipmentTypeEnum::Envelope, $message->order->getShipmentType());
        // Polkurier knows an envelope by the shipment type alone.
        self::assertSame(PackTypeEnum::Standard->value, $this->readPayload($message)['packs'][0]['type']);

        $pallet = (new Pack())
            ->setPackType('PAL')
            ->setLength(120)
            ->setWidth(80)
            ->setHeight(100)
            ->setWeight(200)
            ->setAmount(1);
        self::assertSame(
            ShipmentTypeEnum::Palette,
            $this->createMapper()->map($this->createDraft()->setPacks([$pallet]), 1)->order->getShipmentType(),
        );
    }

    public function testParcelsOfMoreThanOneKindAreRefused(): void
    {
        $draft = $this->createDraft();
        $draft->setPacks([
            ...$draft->getPacks(),
            (new Pack())
                ->setPackType(ParcelTypeMapper::ENVELOPE)
                ->setLength(35)
                ->setWidth(25)
                ->setHeight(2)
                ->setWeight(0.5)
                ->setAmount(1),
        ]);

        $this->expectException(LocalizedException::class);
        $this->createMapper()->map($draft, 1);
    }

    public function testWithoutAChosenSenderTheStoresAddressComesBeforeTheShippingOrigin(): void
    {
        $draft = $this->createDraft()->setSenderAddressId(null);

        $fromStore = $this->createMapper([
            ConfigProvider::XML_PATH_USE_STORE_INFORMATION_ADDRESS,
            ConfigProvider::XML_PATH_USE_ORIGIN_ADDRESS,
        ])->map($draft, 1);
        $fromOrigin = $this->createMapper([ConfigProvider::XML_PATH_USE_ORIGIN_ADDRESS])->map($draft, 1);

        self::assertSame('Rynek', $fromStore->order->getSender()->getStreet());
        self::assertSame('Kraków', $fromStore->order->getSender()->getCity());
        self::assertSame('Marszałkowska', $fromOrigin->order->getSender()->getStreet());
    }

    public function testWithoutAChosenSenderOrAFallbackTheShipmentIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->createMapper([])->map($this->createDraft()->setSenderAddressId(null), 1);
    }

    private function createDraft(): Draft
    {
        return (new Draft())
            ->setCarrierCode('DPD')
            ->setShipmentType('box')
            ->setDescription('Zam. 000000042')
            ->setSenderAddressId(null)
            ->setRecipientPerson('Anna Nowak')
            ->setRecipientCompany(null)
            ->setRecipientStreet('Krótka')
            ->setRecipientHouseNumber('5')
            ->setRecipientFlatNumber('3')
            ->setRecipientPostcode('00-001')
            ->setRecipientCity('Warszawa')
            ->setRecipientCountry('PL')
            ->setRecipientPhone('600700800')
            ->setRecipientEmail('anna@example.com')
            ->setRecipientPointId(null)
            ->setPacks([
                (new Pack())
                    ->setPackType('ST')
                    ->setLength(30)
                    ->setWidth(20)
                    ->setHeight(10)
                    ->setWeight(2.5)
                    ->setAmount(2),
            ])
            ->setServices([])
            ->setAdditionalFields([])
            ->setPickupMode(ShipmentDraftInterface::PICKUP_COURIER);
    }

    private function createDroppedOffDraft(): Draft
    {
        return $this->createDraft()->setPickupMode(ShipmentDraftInterface::PICKUP_DROP_OFF);
    }

    /**
     * @param string[] $flags the switched-on settings; by default, the shipping origin fallback
     */
    private function createMapper(
        array $flags = [ConfigProvider::XML_PATH_USE_ORIGIN_ADDRESS],
    ): CreateOrderMessageMapper
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['shipping/origin/street_line1', 'store', 1, 'Marszałkowska 10'],
            ['shipping/origin/street_line2', 'store', 1, ''],
            ['shipping/origin/city', 'store', 1, 'Warszawa'],
            ['shipping/origin/postcode', 'store', 1, '00-590'],
            ['shipping/origin/country_id', 'store', 1, 'PL'],
            ['general/store_information/street_line1', 'store', 1, 'Rynek 1'],
            ['general/store_information/street_line2', 'store', 1, ''],
            ['general/store_information/city', 'store', 1, 'Kraków'],
            ['general/store_information/postcode', 'store', 1, '31-001'],
            ['general/store_information/country_id', 'store', 1, 'PL'],
            ['general/store_information/name', 'store', 1, 'Sklep'],
            ['general/store_information/phone', 'store', 1, '221234567'],
            ['trans_email/ident_general/email', 'store', 1, 'sklep@example.com'],
            [ConfigProvider::XML_PATH_SENDER_EMAIL, 'store', 1, 'general'],
            [ConfigProvider::XML_PATH_DEFAULT_COD_TYPE, 'store', 1, 'S'],
            [ConfigProvider::XML_PATH_DEFAULT_COD_RETURN_TYPE, 'store', 1, 'BA'],
        ]);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path): bool => \in_array($path, $flags, true),
        );
        $config = new ConfigProvider($scopeConfig, $this->createStub(EncryptorInterface::class));

        $sender = $this->createConfiguredStub(AddressInterface::class, [
            'getPerson' => 'Jan Kowalski', 'getCompany' => 'Magazyn Sp. z o.o.', 'getStreet' => 'Fabryczna',
            'getHouseNumber' => '7', 'getFlatNumber' => null, 'getCity' => 'Łódź', 'getPostcode' => '90-001',
            'getCountry' => 'PL', 'getEmail' => 'magazyn@example.com', 'getPhone' => '501501501',
        ]);
        $cover = $this->createConfiguredStub(AddressInterface::class, [
            'getPerson' => 'Dział zwrotów', 'getCompany' => null, 'getStreet' => 'Zwroty', 'getHouseNumber' => '1',
            'getFlatNumber' => '3', 'getCity' => 'Łódź', 'getPostcode' => '90-002', 'getCountry' => 'PL',
            'getEmail' => 'zwroty@example.com', 'getPhone' => '502502502',
        ]);
        $addresses = $this->createStub(AddressRepositoryInterface::class);
        $addresses->method('get')->willReturnMap([[7, $sender], [8, $cover]]);
        $bankAccounts = $this->createStub(BankAccountRepositoryInterface::class);
        $bankAccounts->method('get')->willReturn(
            $this->createConfiguredStub(BankAccountInterface::class, ['getNumber' => '61109010140000071219812874']),
        );

        return new CreateOrderMessageMapper(
            new SenderMapper(
                $scopeConfig,
                $config,
                new StreetParser(),
                NewingObjectManager::createFactory(AddressBuilderFactory::class),
            ),
            $addresses,
            $bankAccounts,
            $config,
            NewingObjectManager::createFactory(OrderBuilderFactory::class),
            NewingObjectManager::createFactory(PackItemBuilderFactory::class),
            NewingObjectManager::createFactory(AddressBuilderFactory::class),
            NewingObjectManager::createFactory(CodBuilderFactory::class),
            NewingObjectManager::createFactory(PickupBuilderFactory::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readPayload(CreateOrderMessage $message): array
    {
        return (array)\json_decode(SdkSerializer::create()->encode($message), true);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function sortValues(array $values): array
    {
        \ksort($values);

        return $values;
    }
}
