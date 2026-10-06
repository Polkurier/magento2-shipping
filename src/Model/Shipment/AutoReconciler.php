<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
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
    public const int RECHECK_AFTER_MINUTES = 5;

    public function __construct(
        private ShipmentFinder $finder,
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
        $shipments = $this->finder->dueForReconciliation(
            \gmdate(DateFormat::DATETIME_PHP_FORMAT, $now - ShipmentAge::RECONCILE_PENDING_AFTER_MINUTES * 60),
            \gmdate(DateFormat::DATETIME_PHP_FORMAT, $now - self::RECHECK_AFTER_MINUTES * 60),
            self::BATCH_SIZE,
        );

        $settled = 0;
        foreach ($shipments as $shipment) {
            try {
                $result = $this->management->reconcile((int)$shipment->getId());
                if ($result->getState() !== ShipmentInterface::STATE_UNKNOWN) {
                    $settled++;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Polkurier reconciliation failed', [
                    'shipment' => $shipment->getId(),
                    'exception' => $e,
                ]);
                // Only the check time: the row loaded here is older than what the check may have saved.
                $this->shipmentResource->markChecked((int)$shipment->getId(), (string)$this->dateTime->gmtDate());
            }
        }

        return $settled;
    }
}
