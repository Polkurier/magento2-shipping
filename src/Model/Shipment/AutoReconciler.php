<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Settles shipments whose placing was never confirmed, without anyone having to ask.
 *
 * Takes shipments whose outcome is unknown, and pending ones old enough that their request must
 * have been interrupted, and reconciles them: found orders are adopted, and orders still missing
 * after a while are marked as not placed. Least recently checked first, like the status refresh.
 */
readonly class AutoReconciler
{
    public const int BATCH_SIZE = 50;
    /** A pending shipment younger than this may still be waiting for its answer. */
    public const int PENDING_AFTER_MINUTES = 5;
    public const int RECHECK_AFTER_MINUTES = 5;

    public function __construct(
        private CollectionFactory $collectionFactory,
        private ShipmentManagementInterface $management,
        private ShipmentResource $shipmentResource,
        private LoggerInterface $logger,
        private DateTime $dateTime,
    ) {}

    /**
     * @return int how many shipments were settled: adopted, or found not to have been placed
     */
    public function run(): int
    {
        $now = $this->dateTime->gmtTimestamp();
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::STATE, ['in' => [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING]])
            ->addFieldToFilter(ShipmentInterface::CREATED_AT, ['lt' => \gmdate('Y-m-d H:i:s', $now - self::PENDING_AFTER_MINUTES * 60)])
            ->addFieldToFilter(ShipmentInterface::STATUS_CHECKED_AT, [
                ['null' => true],
                ['lt' => \gmdate('Y-m-d H:i:s', $now - self::RECHECK_AFTER_MINUTES * 60)],
            ])
            ->setOrder(ShipmentInterface::STATUS_CHECKED_AT, 'ASC')
            ->setPageSize(self::BATCH_SIZE);

        $settled = 0;
        foreach ($collection->getItems() as $shipment) {
            try {
                $result = $this->management->reconcile((int)$shipment->getId());
                if ($result->getState() !== ShipmentInterface::STATE_UNKNOWN) {
                    $settled++;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Polkurier reconciliation failed', [
                    'shipment' => $shipment->getId(),
                    'error' => $e->getMessage(),
                ]);
                // Only the check time: the row loaded here is older than what the check may have saved.
                $this->shipmentResource->markChecked((int)$shipment->getId(), (string)$this->dateTime->gmtDate());
            }
        }

        return $settled;
    }
}
