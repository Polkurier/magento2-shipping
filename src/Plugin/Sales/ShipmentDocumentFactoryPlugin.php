<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Sales;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Model\Order\ShipmentDocumentFactory;
use Polkurier\Shipping\Model\Shipment\ReservedShipmentNumbers;

/**
 * Gives a Magento shipment the module makes for a Polkurier order the number taken for it before
 * the order was placed, and sent to Polkurier: Magento numbers a shipment without one as it's saved.
 */
readonly class ShipmentDocumentFactoryPlugin
{
    public function __construct(
        private ReservedShipmentNumbers $reservedNumbers,
    ) {}

    /**
     * @param mixed ...$arguments the rest of create()'s
     */
    public function afterCreate(ShipmentDocumentFactory $subject, ShipmentInterface $result, OrderInterface $order, mixed ...$arguments): ShipmentInterface
    {
        $number = $this->reservedNumbers->take((int)$order->getEntityId());
        if ($number !== null && !$result->getIncrementId()) {
            $result->setIncrementId($number);
        }

        return $result;
    }
}
