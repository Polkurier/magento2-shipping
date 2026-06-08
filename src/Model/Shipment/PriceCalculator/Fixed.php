<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\PriceCalculator;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Shipment\PriceCalculatorInterface;
use Polkurier\Shipping\Exception\ConfigException;

class Fixed implements PriceCalculatorInterface
{
    public const string PRICE_INFO_PRICE = 'price';

    /**
     * @param CarrierConfigInterface $carrierConfig
     * @param RateRequest $rateRequest
     *
     * @return float
     * @throws ConfigException
     */
    public function calculatePrice(CarrierConfigInterface $carrierConfig, RateRequest $rateRequest): float
    {
        if (!isset($carrierConfig->getPriceInfo()[static::PRICE_INFO_PRICE])) {
            throw new ConfigException(__('Price for carrier %1 is not set', $carrierConfig->getCarrierCode()));
        }

        return $carrierConfig->getPriceInfo()[static::PRICE_INFO_PRICE];
    }
}
