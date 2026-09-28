<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Mapper;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Model\Address;
use Polkurier\Shipping\Model\Mapper\AddressBookMapper;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Enum\Address\AddressTypeEnum;
use Polkurier\Webservice\Request\Address\GetMessage;
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
        $http = new RecordingClient(self::success([self::REMOTE]));
        $remote = $this->clientPool($http)->addresses()->getAddresses(new GetMessage(AddressTypeEnum::Sender))[0];
        $mapper = new AddressBookMapper();

        $normalized = $mapper->normalize($remote);
        $local = $this->newAddress();
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

    public function testALocalEntryIsSentAsTheApiExpects(): void
    {
        $http = new RecordingClient(self::success(self::REMOTE));
        $mapper = new AddressBookMapper();
        $local = $this->newAddress();
        $local->setExtId('aZ3k')->setName('Magazyn')->setType('SENDER')->setPerson('Jan Kowalski')->setCompany('Sklep')
            ->setStreet('Fabryczna')->setHouseNumber('7')->setFlatNumber(null)->setCity('Łódź')->setPostcode('90-001')
            ->setCountry('PL')->setEmail('magazyn@example.com')->setPhone('501501501')->setDefault(true)->setCover(true);

        $saved = $this->clientPool($http)->addresses()->saveAddress($mapper->toSaveMessage($local));
        $data = $http->lastPayload()['data'];

        self::assertSame('aZ3k', $data['id'], 'An entry already in Polkurier is updated, not duplicated.');
        self::assertSame('7', $data['housenumber']);
        self::assertTrue($data['default']);
        self::assertTrue($data['is_cover_address']);
        self::assertArrayNotHasKey('flatnumber', \array_filter($data, static fn($v) => $v !== null));
        self::assertSame('aZ3k', $mapper->normalize($saved)['id']);
    }

    private function newAddress(): Address
    {
        return (new ObjectManager($this))->getObject(Address::class);
    }
}
