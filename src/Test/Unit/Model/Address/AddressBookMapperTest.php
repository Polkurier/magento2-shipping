<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Address;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Model\Address;
use Polkurier\Shipping\Model\Address\AddressBookMapper;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Builder\Entity\AddressBuilderFactory;
use Polkurier\Webservice\Dto\Request\Address\GetMessage;
use Polkurier\Webservice\Enum\Address\AddressTypeEnum;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(AddressBookMapper::class)]
#[AllowMockObjectsWithoutExpectations]
class AddressBookMapperTest extends SdkTestCase
{
    private const array REMOTE = [
        'id' => 'aZ3k', 'name' => 'Magazyn', 'type' => 'SENDER', 'person' => 'Jan Kowalski', 'company' => 'Sklep',
        'street' => 'Fabryczna', 'housenumber' => '7', 'flatnumber' => '', 'city' => 'Łódź', 'postcode' => '90-001',
        'email' => 'magazyn@example.com', 'phone' => '501501501', 'country' => 'PL', 'province' => '',
        'point_id' => '', 'default' => true, 'shared' => false, 'is_cover_address' => true,
    ];

    public function testAnAddressReadFromPolkurierIsCopiedOntoTheLocalEntry(): void
    {
        $http = new RecordingClient(self::encodeSuccess([self::REMOTE]));
        $remote = $this->createClientPool($http)->getAddresses()->getAddresses(
            new GetMessage(AddressTypeEnum::Sender),
        )[0];
        $mapper = self::createMapper();

        $normalized = $mapper->normalize($remote);
        $local = $this->createNewAddress();
        $mapper->apply($normalized, $local);

        self::assertSame('aZ3k', $local->getExtId());
        self::assertSame('Fabryczna', $local->getStreet());
        self::assertSame('7', $local->getHouseNumber());
        self::assertNull($local->getFlatNumber(), 'Empty strings from the API become nulls.');
        self::assertTrue($local->isDefault());
        self::assertTrue($local->isCover());
        self::assertSame('get_addresses', $http->lastPayload()['apimethod']);
        self::assertSame('SENDER', $http->lastPayload()['data']['type']);
    }

    public function testAProvincePolkurierCantKeepStaysAsMagentoHasIt(): void
    {
        // Polkurier keeps no Polish provinces.
        $http = new RecordingClient(self::encodeSuccess([self::REMOTE]));
        $remote = $this->createClientPool($http)->getAddresses()->getAddresses(
            new GetMessage(AddressTypeEnum::Sender),
        )[0];
        $mapper = self::createMapper();
        $local = $this->createNewAddress()->setProvince('małopolskie');

        $mapper->apply($mapper->normalize($remote), $local);

        self::assertSame('małopolskie', $local->getProvince());
        self::assertSame('Jan Kowalski', $local->getPerson(), 'The rest follows Polkurier.');
    }

    public function testALocalEntryIsSentAsTheApiExpects(): void
    {
        $http = new RecordingClient(self::encodeSuccess(self::REMOTE));
        $mapper = self::createMapper();
        $local = $this->createNewAddress();
        $local->setExtId('aZ3k')
            ->setName('Magazyn')
            ->setType('SENDER')
            ->setPerson('Jan Kowalski')
            ->setCompany('Sklep')
            ->setStreet('Fabryczna')
            ->setHouseNumber('7')
            ->setFlatNumber(null)
            ->setCity('Łódź')
            ->setPostcode('90-001')
            ->setCountry('PL')
            ->setEmail('magazyn@example.com')
            ->setPhone('501501501')
            ->setDefault(true)
            ->setCover(true);

        $saved = $this->createClientPool($http)->getAddresses()->saveAddress($mapper->mapToRecord($local));
        $data = $http->lastPayload()['data'];

        self::assertSame('aZ3k', $data['id'], 'An entry already in Polkurier is updated, not duplicated.');
        self::assertSame('7', $data['housenumber']);
        self::assertTrue($data['default']);
        self::assertTrue($data['is_cover_address']);
        self::assertArrayNotHasKey('flatnumber', \array_filter($data, static fn($v) => $v !== null));
        self::assertSame('aZ3k', $mapper->normalize($saved)[AddressInterface::FIELD_EXT_ID]);
    }

    private function createNewAddress(): Address
    {
        return (new ObjectManager($this))->getObject(Address::class);
    }

    private static function createMapper(): AddressBookMapper
    {
        return new AddressBookMapper(NewingObjectManager::createFactory(AddressBuilderFactory::class));
    }
}
