<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\View as ShipmentView;
use Polkurier\Shipping\Model\Admin\Formatter;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;

/**
 * Tells the admin, in Magento's notifications (the bell, System > Notifications), about each
 * Polkurier shipment that needs attention, so none is missed. Such a shipment is either not placed
 * a day after its last attempt (it's removed a day later), placed without an answer and still not
 * settled by the automatic check an hour later, or placed but still without its Magento shipment an
 * hour later. Each shipment is reported once; a new attempt resets that
 * ({@see \Polkurier\Shipping\Model\Shipment\Placement\PlacementService}). A notice whose shipment
 * no longer needs attention (placed, discarded, removed, settled, or tried again since) is
 * withdrawn rather than left pointing at the past.
 */
readonly class AttentionNotifier
{
    /**
     * How long the automatic checks get before an unsettled shipment is reported.
     */
    public const int SETTLE_HOURS = 1;

    private const string INBOX_TABLE = 'adminnotification_inbox';

    public function __construct(
        private ResourceConnection $resource,
        private ShipmentResource $shipmentResource,
        private NotifierInterface $notifier,
        private UrlInterface $url,
        private Formatter $formatter,
        private DateTime $dateTime,
        private FailedShipmentManager $failedShipmentManager,
    ) {}

    public function notify(): void
    {
        $now = $this->dateTime->gmtTimestamp();
        $noticeBefore = \gmdate(
            DateFormat::DATETIME_PHP_FORMAT,
            $now - FailedShipmentManager::NOTICE_AFTER_HOURS * 3600,
        );
        $settleBefore = \gmdate(DateFormat::DATETIME_PHP_FORMAT, $now - self::SETTLE_HOURS * 3600);
        $connection = $this->resource->getConnection('sales');
        $table = $this->resource->getTableName(ShipmentResource::TABLE_NAME, 'sales');
        $this->withdrawSettled($noticeBefore, $settleBefore);
        $select = $connection->select()
            ->from(
                ['shipment' => $table],
                [
                    ShipmentInterface::FIELD_ENTITY_ID,
                    ShipmentInterface::FIELD_STATE,
                    ShipmentInterface::FIELD_SHIPMENT_ID,
                    ShipmentInterface::FIELD_POLKURIER_NUMBER,
                    ShipmentInterface::FIELD_CREATED_AT,
                    ShipmentInterface::FIELD_LAST_ERROR,
                ],
            )
            ->joinLeft(
                ['sales_order' => $this->resource->getTableName('sales_order', 'sales')],
                'sales_order.entity_id = shipment.' . ShipmentInterface::FIELD_ORDER_ID,
                ['increment_id'],
            )
            ->where('shipment.' . ShipmentInterface::FIELD_ATTENTION_NOTIFIED_AT . ' IS NULL')
            ->where($this->buildAttentionCondition($noticeBefore, $settleBefore));
        foreach ($connection->fetchAll($select) as $row) {
            [$title, $description] = $this->describe($row);
            $this->notifier->addMajor($title, $description, $this->url->getUrl(ShipmentView::ROUTE, [
                'id' => (int)$row[ShipmentInterface::FIELD_ENTITY_ID],
                // Followed outside the session it was made in; the page opens without a key.
                '_nosecret' => true,
            ]));
            $this->shipmentResource->updateColumns((int)$row[ShipmentInterface::FIELD_ENTITY_ID], [
                ShipmentInterface::FIELD_ATTENTION_NOTIFIED_AT => \gmdate(DateFormat::DATETIME_PHP_FORMAT, $now),
            ]);
        }
    }

    /**
     * The SQL condition that a shipment (aliased "shipment") needs attention.
     */
    private function buildAttentionCondition(string $noticeBefore, string $settleBefore): string
    {
        $connection = $this->resource->getConnection('sales');
        $state = 'shipment.' . ShipmentInterface::FIELD_STATE;
        $createdAt = 'shipment.' . ShipmentInterface::FIELD_CREATED_AT;

        return \implode(' OR ', [
            $connection->quoteInto("($state = ?", ShipmentInterface::STATE_FAILED)
                . $connection->quoteInto(" AND $createdAt < ?)", $noticeBefore),
            $connection->quoteInto("($state IN (?)", ShipmentInterface::UNSETTLED_STATES)
                . $connection->quoteInto(" AND $createdAt < ?)", $settleBefore),
            // Placed here: one linked from its track goes without a Magento shipment when that's taken.
            $connection->quoteInto("($state = ?", ShipmentInterface::STATE_CREATED)
                . ' AND shipment.' . ShipmentInterface::FIELD_SHIPMENT_ID . ' IS NULL'
                . ' AND shipment.' . ShipmentInterface::FIELD_REQUEST . ' IS NOT NULL'
                . $connection->quoteInto(" AND $createdAt < ?)", $settleBefore),
        ]);
    }

    /**
     * Withdraws the notices still showing whose shipment is gone, was tried again since, or no
     * longer needs attention. Without Magento's notifications there are none to withdraw.
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
            // Matched by its link: the title is in the language the admin used when it was added.
            ->where('url LIKE ?', '%/' . ShipmentView::ROUTE . '/id/%')) as $notificationId => $url
        ) {
            if (\preg_match('#/' . \preg_quote(ShipmentView::ROUTE, '#') . '/id/(\d+)/?$#', (string)$url, $m)) {
                $shown[(int)$notificationId] = (int)$m[1];
            }
        }
        if ($shown === []) {
            return;
        }
        $connection = $this->resource->getConnection('sales');
        $stillNeeded = \array_map('intval', $connection->fetchCol($connection->select()
            ->from(
                ['shipment' => $this->resource->getTableName(ShipmentResource::TABLE_NAME, 'sales')],
                [ShipmentInterface::FIELD_ENTITY_ID],
            )
            ->where('shipment.' . ShipmentInterface::FIELD_ENTITY_ID . ' IN (?)', \array_values(\array_unique($shown)))
            ->where('shipment.' . ShipmentInterface::FIELD_ATTENTION_NOTIFIED_AT . ' IS NOT NULL')
            ->where($this->buildAttentionCondition($noticeBefore, $settleBefore))));
        $settled = \array_keys(\array_diff($shown, $stillNeeded));
        if ($settled !== []) {
            $inbox->update($inboxTable, ['is_read' => 1, 'is_remove' => 1], ['notification_id IN (?)' => $settled]);
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{0: string, 1: string} the title and the description
     */
    private function describe(array $row): array
    {
        $order = (string)($row['increment_id'] ?? '');
        $when = $this->formatter->formatStoredTime((string)$row[ShipmentInterface::FIELD_CREATED_AT]);
        $reason = \rtrim((string)$row[ShipmentInterface::FIELD_LAST_ERROR], '. ');

        return match ((string)$row[ShipmentInterface::FIELD_STATE]) {
            ShipmentInterface::STATE_FAILED => [
                (string)__('Polkurier: a shipment of order #%1 wasn\'t placed', $order),
                (string)__(
                    'It failed on Polkurier\'s side on %1: %2. Send it again or discard it; '
                    . 'otherwise it\'s removed on %3.',
                    $when,
                    $reason,
                    $this->formatter->formatStoredTime(
                        $this->failedShipmentManager->calculateExpiry(
                            (string)$row[ShipmentInterface::FIELD_CREATED_AT],
                        ),
                    ),
                ),
            ],
            ShipmentInterface::STATE_CREATED => [
                (string)__('Polkurier: order #%1 is placed without its Magento shipment', $order),
                (string)__(
                    'Polkurier order %1 was placed on %2, but its Magento shipment couldn\'t be created: %3. '
                    . 'Create it from the shipment\'s page.',
                    (string)$row[ShipmentInterface::FIELD_POLKURIER_NUMBER],
                    $when,
                    $reason,
                ),
            ],
            default => [
                (string)__('Polkurier: a shipment of order #%1 got no answer', $order),
                (string)__(
                    'Polkurier didn\'t answer when it was placed, on %1, and it hasn\'t been found since: %2. '
                    . 'If you know its Polkurier order, link it by hand on the shipment\'s details page.',
                    $when,
                    $reason,
                ),
            ],
        };
    }
}
