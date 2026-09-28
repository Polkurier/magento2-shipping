<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * Finds the Polkurier shipment a waybill belongs to, for its tracking page and carrier.
 */
class TrackingLookup
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {}

    public function findByWaybill(string $waybill): ?ShipmentInterface
    {
        if ($waybill === '') {
            return null;
        }
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::WAYBILLS, ['finset' => $waybill])
            ->setOrder(ShipmentInterface::ENTITY_ID, 'DESC')
            ->setPageSize(1);
        foreach ($collection->getItems() as $shipment) {
            return $shipment;
        }

        return null;
    }
}
