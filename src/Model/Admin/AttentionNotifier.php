<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Shipment\NotPlaced;

/**
 * Tells the admin, in Magento's notifications (the bell, System > Notifications), about each
 * Polkurier shipment that needs someone, so none is missed: not placed a day after its last
 * attempt (removed a day later), placed without an answer and still not settled by the automatic
 * check an hour on, or placed without its Magento shipment an hour on. Once per shipment: a new
 * attempt clears it (Placing). A notice whose shipment no longer needs anyone (placed, discarded,
 * removed, settled, tried again since) is withdrawn, rather than left pointing at what's past.
 */
readonly class AttentionNotifier
{
    public const string NOTIFIED_AT = 'attention_notified_at';

    /**
     * How long the automatic checks get before an unsettled shipment is reported.
     */
    public const int SETTLE_HOURS = 1;

    private const string INBOX_TABLE = 'adminnotification_inbox';
    private const string VIEW_PATH = 'polkurier/shipment/view';

    public function __construct(
        private ResourceConnection $resource,
        private NotifierInterface $notifier,
        private UrlInterface $url,
        private TimezoneInterface $timezone,
        private DateTime $dateTime,
        private NotPlaced $notPlaced,
    ) {}

    /**
     * @return int how many were notified
     */
    public function notify(): int
    {
        $now = $this->dateTime->gmtTimestamp();
        $noticeBefore = \gmdate('Y-m-d H:i:s', $now - NotPlaced::NOTICE_AFTER_HOURS * 3600);
        $settleBefore = \gmdate('Y-m-d H:i:s', $now - self::SETTLE_HOURS * 3600);
        $connection = $this->resource->getConnection('sales');
        $table = $this->resource->getTableName(ShipmentResource::TABLE_NAME, 'sales');
        $this->withdrawSettled($noticeBefore, $settleBefore);
        $select = $connection->select()
            ->from(['shipment' => $table], ['entity_id', 'state', 'shipment_id', 'polkurier_number', 'created_at', 'last_error'])
            ->joinLeft(
                ['sales_order' => $this->resource->getTableName('sales_order', 'sales')],
                'sales_order.entity_id = shipment.order_id',
                ['increment_id'],
            )
            ->where('shipment.' . self::NOTIFIED_AT . ' IS NULL')
            ->where($this->needsSomeone($noticeBefore, $settleBefore));
        $notified = 0;
        foreach ($connection->fetchAll($select) as $row) {
            [$title, $description] = $this->describe($row);
            $this->notifier->addMajor($title, $description, $this->url->getUrl(self::VIEW_PATH, [
                'id' => (int)$row['entity_id'],
                // Followed outside the session it was made in; the page opens without a key.
                '_nosecret' => true,
            ]));
            $connection->update($table, [self::NOTIFIED_AT => \gmdate('Y-m-d H:i:s', $now)], ['entity_id = ?' => (int)$row['entity_id']]);
            $notified++;
        }

        return $notified;
    }

    /**
     * The condition on a shipment (as "shipment") that it needs someone.
     */
    private function needsSomeone(string $noticeBefore, string $settleBefore): string
    {
        $connection = $this->resource->getConnection('sales');

        return \implode(' OR ', [
            $connection->quoteInto('(shipment.state = ?', ShipmentInterface::STATE_FAILED) . $connection->quoteInto(' AND shipment.created_at < ?)', $noticeBefore),
            $connection->quoteInto('(shipment.state IN (?)', [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING]) . $connection->quoteInto(' AND shipment.created_at < ?)', $settleBefore),
            $connection->quoteInto('(shipment.state = ? AND shipment.shipment_id IS NULL', ShipmentInterface::STATE_CREATED) . $connection->quoteInto(' AND shipment.created_at < ?)', $settleBefore),
        ]);
    }

    /**
     * Withdraws the notices still showing whose shipment is gone, was tried again since, or no
     * longer needs anyone. Without Magento's notifications there are none to withdraw.
     */
    private function withdrawSettled(string $noticeBefore, string $settleBefore): void
    {
        $inbox = $this->resource->getConnection();
        $inboxTable = $this->resource->getTableName(self::INBOX_TABLE);
        if (!$inbox->isTableExists($inboxTable)) {
            return;
        }
        $shown = [];
        foreach ($inbox->fetchPairs($inbox->select()
            ->from($inboxTable, ['notification_id', 'url'])
            ->where('is_remove = 0')
            ->where('title LIKE ?', 'Polkurier: %')
            ->where('url LIKE ?', '%/' . self::VIEW_PATH . '/id/%')) as $notificationId => $url
        ) {
            if (\preg_match('#/' . \preg_quote(self::VIEW_PATH, '#') . '/id/(\d+)/?$#', (string)$url, $m)) {
                $shown[(int)$notificationId] = (int)$m[1];
            }
        }
        if ($shown === []) {
            return;
        }
        $connection = $this->resource->getConnection('sales');
        $stillNeeded = \array_map('intval', $connection->fetchCol($connection->select()
            ->from(['shipment' => $this->resource->getTableName(ShipmentResource::TABLE_NAME, 'sales')], ['entity_id'])
            ->where('shipment.entity_id IN (?)', \array_values(\array_unique($shown)))
            ->where('shipment.' . self::NOTIFIED_AT . ' IS NOT NULL')
            ->where($this->needsSomeone($noticeBefore, $settleBefore))));
        $settled = \array_keys(\array_diff($shown, $stillNeeded));
        if ($settled !== []) {
            $inbox->update($inboxTable, ['is_read' => 1, 'is_remove' => 1], ['notification_id IN (?)' => $settled]);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array{0: string, 1: string} the title and the description
     */
    private function describe(array $row): array
    {
        $order = (string)($row['increment_id'] ?? '');
        $when = $this->format((string)$row['created_at']);
        $reason = \rtrim((string)$row['last_error'], '. ');

        return match ((string)$row['state']) {
            ShipmentInterface::STATE_FAILED => [
                (string)\__('Polkurier: a shipment of order #%1 wasn\'t placed', $order),
                (string)\__(
                    'It failed on Polkurier\'s side on %1: %2. Send it again or discard it; otherwise it\'s removed on %3.',
                    $when,
                    $reason,
                    $this->format($this->notPlaced->expiresAfter((string)$row['created_at'])),
                ),
            ],
            ShipmentInterface::STATE_CREATED => [
                (string)\__('Polkurier: order #%1 is placed without its Magento shipment', $order),
                (string)\__(
                    'Polkurier order %1 was placed on %2, but its Magento shipment couldn\'t be created: %3. Create it from the shipment\'s page.',
                    (string)$row['polkurier_number'],
                    $when,
                    $reason,
                ),
            ],
            default => [
                (string)\__('Polkurier: a shipment of order #%1 got no answer', $order),
                (string)\__(
                    'Polkurier didn\'t answer when it was placed, on %1, and it hasn\'t been settled since: %2. Reconcile it, or link it to its Polkurier order by hand.',
                    $when,
                    $reason,
                ),
            ],
        };
    }

    private function format(string $utc): string
    {
        return $this->timezone->formatDateTime(
            new \DateTimeImmutable($utc, new \DateTimeZone('UTC')),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT,
        );
    }
}
