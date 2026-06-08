<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Shipment\PriceCalculatorInterface;

class PriceCalculatorPool implements PriceCalculatorInterface
{
    /**
     * @var PriceCalculatorInterface[]
     */
    private array $priceCalculators = [];

    /**
     * @param PriceCalculatorInterface[] $priceCalculators
     */
    public function __construct(array $priceCalculators = [])
    {
        foreach ($priceCalculators as $strategy => $priceCalculator) {
            if ($priceCalculator instanceof PriceCalculatorInterface) {
                $this->priceCalculators[$strategy] = $priceCalculator;
            }
        }
    }

    /**
     * @param CarrierConfigInterface $carrierConfig
     * @param RateRequest $rateRequest
     *
     * @return float
     */
    public function calculatePrice(CarrierConfigInterface $carrierConfig, RateRequest $rateRequest): float
    {
        return $this->priceCalculators[$carrierConfig->getPricingModel()->value]
            ->calculatePrice($carrierConfig, $rateRequest);
    }
}
