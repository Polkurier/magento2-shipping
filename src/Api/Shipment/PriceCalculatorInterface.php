<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Shipment;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;

interface PriceCalculatorInterface
{
    /**
     * @param CarrierConfigInterface $carrierConfig
     * @param RateRequest $rateRequest
     *
     * @return float
     */
    public function calculatePrice(CarrierConfigInterface $carrierConfig, RateRequest $rateRequest): float;
}
