<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Address;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Address;
use Polkurier\Shipping\Model\Address\AddressBookMapper;
use Polkurier\Shipping\Model\Address\AddressSyncAdapter;
use Polkurier\Shipping\Model\AddressFactory;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Builder\Address\SaveMessageBuilderFactory;
use Polkurier\Webservice\Request\Address\DeleteMessage;
use Polkurier\Webservice\Request\Address\SaveMessage;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(AddressSyncAdapter::class)]
#[CoversClass(ApiEventDispatcher::class)]
#[AllowMockObjectsWithoutExpectations]
class AddressSyncAdapterTest extends SdkTestCase
{
    private RecordingEventManager $events;

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

        self::assertSame('aZ3k', $remote[AddressInterface::FIELD_EXT_ID]);
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

    protected function setUp(): void
    {
        $this->events = new RecordingEventManager();
    }

    private function adapter(RecordingClient $http): AddressSyncAdapter
    {
        return new AddressSyncAdapter(
            $this->clientPool($http),
            new AddressBookMapper(NewingObjectManager::factory(SaveMessageBuilderFactory::class)),
            $this->createStub(CollectionFactory::class),
            $this->createStub(AddressFactory::class),
            $this->createStub(AddressRepositoryInterface::class),
            new ApiEventDispatcher($this->events, $this->createStub(Logger::class)),
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
