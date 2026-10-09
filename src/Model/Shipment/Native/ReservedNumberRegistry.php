<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Native;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
/**
 * Passes the number reserved for a Magento shipment to the shipment as Magento creates it;
 * Magento's ship order service has no way to pass it. The number is registered for the order just
 * before (expect()) and taken when the shipment is created
 * ({@see \Polkurier\Shipping\Plugin\Sales\ShipmentDocumentFactoryPlugin}).
 */
class ReservedNumberRegistry implements ResetAfterRequestInterface
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

    public function _resetState(): void
    {
        $this->expected = [];
    }
}
