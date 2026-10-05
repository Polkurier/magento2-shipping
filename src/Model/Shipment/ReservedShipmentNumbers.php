<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

/**
 * Hands the number taken for a Magento shipment to it as Magento makes it, which Magento's ship
 * order service gives no way to pass: expected for the order just before, taken as the shipment
 * is made ({@see \Polkurier\Shipping\Plugin\Sales\ShipmentDocumentFactoryPlugin}).
 */
class ReservedShipmentNumbers
{
    /**
     * @var array<int, string> by order id
     */
    private array $expected = [];

    public function expect(int $orderId, string $number): void
    {
        $this->expected[$orderId] = $number;
    }

    public function take(int $orderId): ?string
    {
        $number = $this->expected[$orderId] ?? null;
        unset($this->expected[$orderId]);

        return $number;
    }

    public function forget(int $orderId): void
    {
        unset($this->expected[$orderId]);
    }
}
