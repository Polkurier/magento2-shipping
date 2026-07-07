<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider\Shipment;

use Magento\Sales\Api\Data\OrderInterface;

readonly class DefaultInsuranceProvider
{
    /**
     * @param OrderInterface|null $order
     *
     * @return float|null
     */
    public function getDefaultInsuranceAmount(?OrderInterface $order = null): ?float
    {
        return $order?->getGrandTotal();
    }
}
