<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Config;
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
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return int how many shipments were refreshed
     */
    public function refreshPending(): int
    {
        $storeIds = $this->getStoresWithStatusSync();
        if ($storeIds === []) {
            return 0;
        }
        $checkedBefore = \gmdate('Y-m-d H:i:s', \time() - self::MIN_AGE_MINUTES * 60);
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::STATE, ShipmentInterface::STATE_CREATED)
            ->addFieldToFilter(ShipmentInterface::IS_FINAL, ['eq' => 0])
            ->addFieldToFilter(ShipmentInterface::STORE_ID, ['in' => $storeIds])
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
            } catch (LocalizedException $e) {
                $this->logger->warning('Polkurier status refresh failed', [
                    'shipment' => $shipment->getId(),
                    'error' => $e->getMessage(),
                ]);
                $this->shipmentRepository->save($shipment->setStatusCheckedAt(\gmdate('Y-m-d H:i:s')));
            }
        }

        return $refreshed;
    }

    /**
     * @return int[]
     */
    private function getStoresWithStatusSync(): array
    {
        $storeIds = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($this->config->isStatusSyncEnabled((int)$store->getId())) {
                $storeIds[] = (int)$store->getId();
            }
        }

        return $storeIds;
    }
}
