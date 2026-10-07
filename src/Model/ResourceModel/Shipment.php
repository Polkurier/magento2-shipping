<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * The polkurier_shipment table.
 */
class Shipment extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_shipment';

    /**
     * Records when a shipment was last checked, and nothing else: whatever the check managed to
     * save before failing stays as it is.
     *
     * @param string $checkedAt UTC, Y-m-d H:i:s
     */
    public function markChecked(int $shipmentId, string $checkedAt): void
    {
        $this->updateColumns($shipmentId, [ShipmentInterface::FIELD_STATUS_CHECKED_AT => $checkedAt]);
    }

    /**
     * Writes the given columns of one shipment, leaving the rest of the row as it's stored: for
     * a request that loaded the row a while ago and changes only part of it.
     *
     * @param array<string, scalar|null> $values stored values by column
     */
    public function updateColumns(int $shipmentId, array $values): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            $values,
            [$this->getIdFieldName() . ' = ?' => $shipmentId],
        );
    }

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, ShipmentInterface::FIELD_ENTITY_ID);
    }
}
