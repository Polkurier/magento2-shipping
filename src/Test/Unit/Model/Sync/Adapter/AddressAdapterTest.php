<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sync\Adapter;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Enum\ApiEventEnum;
use Polkurier\Shipping\Model\Address;
use Polkurier\Shipping\Model\AddressFactory;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\Mapper\AddressBookMapper;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory;
use Polkurier\Shipping\Model\Sync\Adapter\AddressAdapter;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Request\Address\DeleteMessage;
use Polkurier\Webservice\Request\Address\SaveMessage;
use Polkurier\Webservice\Testing\RecordingClient;
use Psr\Log\NullLogger;

#[CoversClass(AddressAdapter::class)]
#[CoversClass(ApiEventDispatcher::class)]
#[AllowMockObjectsWithoutExpectations]
class AddressAdapterTest extends SdkTestCase
{
    private RecordingEventManager $events;

    protected function setUp(): void
    {
        $this->events = new RecordingEventManager();
    }

    public function testSavingAnAddressOnTheAccountIsReported(): void
    {
        $http = new RecordingClient(self::success([
            'id' => 'aZ3k', 'name' => 'Magazyn', 'type' => 'SENDER', 'person' => 'Jan Kowalski',
            'company' => '', 'street' => 'Fabryczna', 'housenumber' => '7', 'flatnumber' => '',
            'city' => 'Łódź', 'postcode' => '90-001', 'email' => 'magazyn@example.com',
            'phone' => '501501501', 'country' => 'PL', 'province' => '', 'point_id' => '',
            'default' => false, 'shared' => false, 'is_cover_address' => false,
        ]));
        $address = $this->address();

        $remote = $this->adapter($http)->push($address);

        self::assertSame('aZ3k', $remote['id']);
        self::assertSame('save_address', $http->lastPayload()['apimethod']);
        $events = $this->events->of(ApiEventEnum::AddressSaveAfter);
        self::assertCount(1, $events);
        self::assertNull($events[0]['store_id'], 'The account\'s, not a store\'s.');
        self::assertSame($address, $events[0]['address']);
        self::assertTrue($events[0]['is_new']);
        self::assertInstanceOf(SaveMessage::class, $events[0]['request']);
        self::assertSame('aZ3k', $events[0]['response']->getId());
    }

    public function testDeletingAnAddressFromTheAccountIsReported(): void
    {
        $http = new RecordingClient(self::success(true));
        $address = $this->address()->setExtId('aZ3k');

        $this->adapter($http)->deleteRemote($address);

        self::assertSame('delete_address', $http->lastPayload()['apimethod']);
        $events = $this->events->of(ApiEventEnum::AddressDeleteAfter);
        self::assertCount(1, $events);
        self::assertSame($address, $events[0]['address']);
        self::assertInstanceOf(DeleteMessage::class, $events[0]['request']);
    }

    private function adapter(RecordingClient $http): AddressAdapter
    {
        return new AddressAdapter(
            $this->clientPool($http),
            new AddressBookMapper(),
            $this->createStub(CollectionFactory::class),
            $this->createStub(AddressFactory::class),
            $this->createStub(AddressRepositoryInterface::class),
            new ApiEventDispatcher($this->events, new NullLogger()),
        );
    }

    private function address(): Address
    {
        /** @var Address $address */
        $address = (new ObjectManager($this))->getObject(Address::class);

        return $address->setName('Magazyn')->setPerson('Jan Kowalski')->setStreet('Fabryczna')
            ->setHouseNumber('7')->setPostcode('90-001')->setCity('Łódź')->setEmail('magazyn@example.com')
            ->setPhone('501501501')->setType('SENDER');
    }
}
