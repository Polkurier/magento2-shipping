<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Helper;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\PolkurierCarrier;

readonly class ShipmentHelper
{
    /**
     * @param CartInterface $quote
     *
     * @return bool
     */
    public static function isQuotePolkurierShipment(CartInterface $quote): bool
    {
        return \explode('_', $quote->getShippingAddress()->getShippingMethod())[0] === PolkurierCarrier::CODE;
    }

    /**
     * @param OrderInterface $order
     *
     * @return bool
     */
    public static function isOrderPolkurierShipment(OrderInterface $order): bool
    {
        return \explode('_', $order->getShippingMethod())[0] === PolkurierCarrier::CODE;
    }

    /**
     * @param ShipmentInterface $shipment
     *
     * @return bool
     */
    public static function isShipmentPolkurierShipment(ShipmentInterface $shipment): bool
    {
        return \explode('_', $shipment->getOrder()->getShippingMethod())[0] === PolkurierCarrier::CODE;
    }
}
