<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Payment\Checks;

use Magento\Payment\Model\Checks\SpecificationInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Polkurier\Shipping\Helper\ShipmentHelper;

readonly class CanUseForDelivery implements SpecificationInterface
{
    /**
     * @param ShipmentHelper $shipmentHelper
     * @param array $codPayments
     */
    public function __construct(
        /**
         * @var ShipmentHelper
         */
        private ShipmentHelper $shipmentHelper,

        /**
         * @var string[]
         */
        private array $codPayments = [],
    ) {}

    /**
     * @param MethodInterface $paymentMethod
     * @param Quote $quote
     *
     * @return bool
     */
    public function isApplicable(MethodInterface $paymentMethod, Quote $quote)
    {
        if (\in_array($paymentMethod->getCode(), $this->codPayments)
            && $this->shipmentHelper->isQuotePolkurierShipment($quote)
        ) {
            return $this->shipmentHelper->isCodAvailableForCarrier($this->shipmentHelper->getCarrierCode($quote))
                && $quote->getExtensionAttributes()->getPolkurierShipmentData()->isCodAvailable();
        }

        return true;
    }
}
