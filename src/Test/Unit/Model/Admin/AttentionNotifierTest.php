<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Admin;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Admin\AttentionNotifier;
use Polkurier\Shipping\Model\Shipment\NotPlaced;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;

#[CoversClass(AttentionNotifier::class)]
#[AllowMockObjectsWithoutExpectations]
class AttentionNotifierTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $log = [];

    public function testEachShipmentNeedingSomeoneIsNotifiedOnceWithALinkToIt(): void
    {
        $count = $this->notifier([
            ['entity_id' => '5', 'state' => 'failed', 'shipment_id' => null, 'polkurier_number' => null, 'created_at' => '2026-10-01 07:00:00', 'last_error' => 'Przerwa techniczna.', 'increment_id' => '000000008'],
            ['entity_id' => '9', 'state' => 'created', 'shipment_id' => null, 'polkurier_number' => '18015-1', 'created_at' => '2026-10-02 08:00:00', 'last_error' => 'Deadlock', 'increment_id' => '000000013'],
        ])->notify();

        self::assertSame(2, $count);
        self::assertSame([
            "major | Polkurier: a shipment of order #000000008 wasn't placed | It failed on Polkurier's side on 2026-10-01 07:00:00: Przerwa techniczna. Send it again or discard it; otherwise it's removed on 2026-10-03 07:00:00. | view 5 nosecret",
            'notified 5',
            'major | Polkurier: order #000000013 is placed without its Magento shipment | Polkurier order 18015-1 was placed on 2026-10-02 08:00:00, but its Magento shipment couldn\'t be created: Deadlock. Create it from the shipment\'s page. | view 9 nosecret',
            'notified 9',
        ], $this->log);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function notifier(array $rows): AttentionNotifier
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('quoteInto')->willReturnCallback(static fn(string $text): string => $text);
        $connection->method('fetchAll')->willReturn($rows);
        $connection->method('update')->willReturnCallback(function (string $table, array $data, array $where): int {
            $this->log[] = 'notified ' . \current($where);

            return 1;
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $notifier = $this->createStub(NotifierInterface::class);
        $notifier->method('addMajor')->willReturnCallback(function (string $title, string $description, string $url): NotifierInterface {
            $this->log[] = 'major | ' . $title . ' | ' . $description . ' | ' . $url;

            return $this->createStub(NotifierInterface::class);
        });
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $path, array $params): string => 'view ' . $params['id'] . (!empty($params['_nosecret']) ? ' nosecret' : ''),
        );
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnCallback(static fn(\DateTimeInterface $date): string => $date->format('Y-m-d H:i:s'));
        $clock = new FixedDateTime((int)\strtotime('2026-10-02 10:00:00 UTC'));
        $notPlaced = $this->getMockBuilder(NotPlaced::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        (new \ReflectionProperty(NotPlaced::class, 'dateTime'))->setValue($notPlaced, $clock);

        return new AttentionNotifier($resource, $notifier, $url, $timezone, $clock, $notPlaced);
    }
}
