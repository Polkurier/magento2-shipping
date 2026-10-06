<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Psr\Log\LoggerInterface;

/**
 * Keeps delivery and cash-on-delivery states current for shipments still in motion.
 *
 * Each run takes the batch checked longest ago. Everything that decides whether a shipment is due
 * is in the query, and every shipment taken is marked as checked, even when the check fails:
 * a shipment that is skipped or failing must not stay first in line and crowd out the rest.
 */
readonly class StatusRefresher
{
    public const int BATCH_SIZE = 200;

    public function __construct(
        private ShipmentFinder $finder,
        private ShipmentManagementInterface $management,
        private ShipmentResource $shipmentResource,
        private Config $config,
        private LoggerInterface $logger,
        private DateTime $dateTime,
    ) {}

    /**
     * The scheduled run: the shipments not checked since the previous one. Whether the scheduled
     * updates are on is the cron job's to check.
     *
     * @return int how many shipments were refreshed
     */
    public function refreshPending(): int
    {
        // A minute short of the interval, so that a shipment checked by the previous run is due.
        $minAge = \max(1, $this->config->getStatusIntervalMinutes() - 1);

        return $this->refresh($minAge)['refreshed'];
    }

    /**
     * Asked for in the admin: the shipments still in motion, however recently checked, oldest
     * check first.
     *
     * @return array{refreshed: int, failed: int}
     */
    public function refreshNow(): array
    {
        return $this->refresh(0);
    }

    /**
     * @return array{refreshed: int, failed: int}
     */
    private function refresh(int $minAgeMinutes): array
    {
        $checkedBefore = $minAgeMinutes > 0
            ? \gmdate(DateFormat::DATETIME_PHP_FORMAT, $this->dateTime->gmtTimestamp() - $minAgeMinutes * 60)
            : null;
        $refreshed = 0;
        $failed = 0;
        foreach ($this->finder->dueForStatusCheck($checkedBefore, self::BATCH_SIZE) as $shipment) {
            try {
                $this->management->refreshStatus((int)$shipment->getId());
                $refreshed++;
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->warning('Polkurier status refresh failed', [
                    'shipment' => $shipment->getId(),
                    'exception' => $e,
                ]);
                // Only the check time: the row loaded here is older than what the check may have saved.
                $this->shipmentResource->markChecked((int)$shipment->getId(), (string)$this->dateTime->gmtDate());
            }
        }

        return ['refreshed' => $refreshed, 'failed' => $failed];
    }
}
