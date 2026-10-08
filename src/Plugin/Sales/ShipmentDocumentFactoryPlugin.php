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
use Polkurier\Shipping\Model\Shipment\Native\ReservedNumberRegistry;

/**
 * When the module makes a Magento shipment for a Polkurier order, gives it the number reserved
 * before the order was placed and sent to Polkurier. Without it, Magento would number the shipment
 * as it's saved.
 */
readonly class ShipmentDocumentFactoryPlugin
{
    public function __construct(
        private ReservedNumberRegistry $reservedNumberRegistry,
    ) {}

    /**
     * @param mixed ...$arguments the rest of create()'s
     */
    public function afterCreate(
        ShipmentDocumentFactory $subject,
        ShipmentInterface $result,
        OrderInterface $order,
        mixed ...$arguments,
    ): ShipmentInterface {
        // A shipment numbered already leaves the reserved number for the one it was reserved for.
        if ($result->getIncrementId()) {
            return $result;
        }
        $number = $this->reservedNumberRegistry->take((int)$order->getEntityId());
        if ($number !== null) {
            $result->setIncrementId($number);
        }

        return $result;
    }
}
