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
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Keeps delivery and cash-on-delivery states current for shipments still in motion.
 *
 * Each run takes the batch checked longest ago. Everything that decides whether a shipment is due
 * is in the query, and every shipment taken is marked as checked, even when the check fails:
 * a shipment that is skipped or failing must not stay first in line and crowd out the rest.
 */
class StatusRefresher
{
    public const int BATCH_SIZE = 200;
    public const int MIN_AGE_MINUTES = 30;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ShipmentManagementInterface $management,
        private readonly ShipmentResource $shipmentResource,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly DateTime $dateTime,
    ) {}

    /**
     * @return int how many shipments were refreshed
     */
    public function refreshPending(): int
    {
        if (!$this->config->isStatusSyncEnabled()) {
            return 0;
        }
        $checkedBefore = \gmdate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() - self::MIN_AGE_MINUTES * 60);
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::STATE, ShipmentInterface::STATE_CREATED)
            ->addFieldToFilter(ShipmentInterface::IS_FINAL, ['eq' => 0])
            ->addFieldToFilter(ShipmentInterface::STATUS_CHECKED_AT, [
                ['null' => true],
                ['lt' => $checkedBefore],
            ])
            ->setOrder(ShipmentInterface::STATUS_CHECKED_AT, 'ASC')
            ->setPageSize(self::BATCH_SIZE);

        $refreshed = 0;
        foreach ($collection->getItems() as $shipment) {
            try {
                $this->management->refreshStatus((int)$shipment->getId());
                $refreshed++;
            } catch (\Throwable $e) {
                $this->logger->warning('Polkurier status refresh failed', [
                    'shipment' => $shipment->getId(),
                    'error' => $e->getMessage(),
                ]);
                // Only the check time: the row loaded here is older than what the check may have saved.
                $this->shipmentResource->markChecked((int)$shipment->getId(), (string)$this->dateTime->gmtDate());
            }
        }

        return $refreshed;
    }

}
