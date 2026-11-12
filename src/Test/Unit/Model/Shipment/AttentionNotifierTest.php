<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Shipment\AttentionNotifier;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
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
        $this->createNotifier([
            [
                'entity_id' => '5',
                'state' => 'failed',
                'shipment_id' => null,
                'polkurier_number' => null,
                'created_at' => '2026-10-01 07:00:00',
                'last_error' => 'Przerwa techniczna.',
                'increment_id' => '000000008',
            ],
            [
                'entity_id' => '9',
                'state' => 'created',
                'shipment_id' => null,
                'polkurier_number' => '18015-1',
                'created_at' => '2026-10-02 08:00:00',
                'last_error' => 'Deadlock',
                'increment_id' => '000000013',
            ],
        ])->notify();

        self::assertSame([
            "major | Polkurier: a shipment of order #000000008 wasn't placed | It failed on Polkurier's side on "
            . "2026-10-01 07:00:00: Przerwa techniczna. Send it again or discard it; otherwise it's removed on "
            . '2026-10-03 07:00:00. | view 5 nosecret',
            'notified 5',
            'major | Polkurier: order #000000013 is placed without its Magento shipment | Polkurier order 18015-1 '
            . 'was placed on 2026-10-02 08:00:00, but its Magento shipment couldn\'t be created: Deadlock. '
            . 'Create it from the shipment\'s page. | view 9 nosecret',
            'notified 9',
        ], $this->log);
    }

    public function testNoticesWhoseShipmentNoLongerNeedsSomeoneAreWithdrawn(): void
    {
        $this->createNotifier([], [
            3 => 'http://shop.test/admin/polkurier/shipment/view/id/5/',
            4 => 'http://shop.test/admin/polkurier/shipment/view/id/8/',
            6 => 'http://shop.test/admin/polkurier/shipment/view/id/12/',
        ], [8])->notify();

        self::assertSame(['withdrawn 3,6'], $this->log);
    }

    public function testNothingIsWithdrawnWithoutMagentosNotifications(): void
    {
        $this->createNotifier([], null)->notify();

        self::assertSame([], $this->log);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<int, string>|null $shown the notices showing, by id; null: no notifications table
     * @param int[] $stillNeeded the shipments of those that still need someone
     */
    private function createNotifier(array $rows, ?array $shown = [], array $stillNeeded = []): AttentionNotifier
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('quoteInto')->willReturnCallback(static fn(string $text): string => $text);
        $connection->method('fetchAll')->willReturn($rows);
        $connection->method('isTableExists')->willReturn($shown !== null);
        $connection->method('fetchPairs')->willReturn($shown ?? []);
        $connection->method('fetchCol')->willReturn(\array_map('strval', $stillNeeded));
        $connection->method('update')->willReturnCallback(function (string $table, array $data, array $where): int {
            if ($table === 'adminnotification_inbox') {
                self::assertSame(['is_read' => 1, 'is_remove' => 1], $data);
                $this->log[] = 'withdrawn ' . \implode(',', \current($where));
            }

            return 1;
        });
        $shipmentResource = $this->createStub(ShipmentResource::class);
        $shipmentResource->method('updateColumns')->willReturnCallback(function (int $shipmentId, array $values): void {
            self::assertSame(['attention_notified_at' => '2026-10-02 10:00:00'], $values);
            $this->log[] = 'notified ' . $shipmentId;
        });
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $notifier = $this->createStub(NotifierInterface::class);
        $notifier->method('addMajor')
            ->willReturnCallback(function (string $title, string $description, string $url): NotifierInterface {
                $this->log[] = 'major | ' . $title . ' | ' . $description . ' | ' . $url;

                return $this->createStub(NotifierInterface::class);
            });
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $path, array $params): string => 'view ' . $params['id']
                . (!empty($params['_nosecret']) ? ' nosecret' : ''),
        );
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnArgument(0);
        $format = new Formatter(
            $timezone,
            $this->createStub(PriceCurrencyInterface::class),
            $this->createStub(ResolverInterface::class),
        );
        $clock = new FixedDateTime((int)\strtotime('2026-10-02 10:00:00 UTC'));
        $failedShipmentManager = $this->getMockBuilder(FailedShipmentManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        (new \ReflectionProperty(FailedShipmentManager::class, 'dateTime'))->setValue($failedShipmentManager, $clock);

        return new AttentionNotifier(
            $resource,
            $shipmentResource,
            $notifier,
            $url,
            $format,
            $clock,
            $failedShipmentManager,
        );
    }
}
