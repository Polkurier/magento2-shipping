<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\OrderSnapshot;

/**
 * Sets the is_final flag on shipments recorded before it existed, with the same rule that sets it
 * on every status update since.
 */
class MarkFinishedShipments implements DataPatchInterface
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CollectionFactory $collectionFactory,
        private readonly OrderSnapshot $snapshot,
    ) {}

    public function apply(): static
    {
        $this->moduleDataSetup->startSetup();
        $lastId = 0;
        do {
            // Read by id rather than by page: rows marked below would shift the pages under the loop.
            $collection = $this->collectionFactory->create()
                ->addFieldToFilter(ShipmentInterface::IS_FINAL, ['eq' => 0])
                ->addFieldToFilter('entity_id', ['gt' => $lastId])
                ->setOrder('entity_id', 'ASC')
                ->setPageSize(self::BATCH_SIZE);
            $finished = [];
            $count = 0;
            foreach ($collection->getItems() as $shipment) {
                $count++;
                $lastId = (int)$shipment->getId();
                if ($this->snapshot->isFinal($shipment)) {
                    $finished[] = $lastId;
                }
            }
            if ($finished !== []) {
                $this->moduleDataSetup->getConnection()->update(
                    $this->moduleDataSetup->getTable('polkurier_shipment'),
                    [ShipmentInterface::IS_FINAL => 1],
                    ['entity_id IN (?)' => $finished],
                );
            }
        } while ($count === self::BATCH_SIZE);
        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
