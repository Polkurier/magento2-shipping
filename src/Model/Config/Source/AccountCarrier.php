<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Every carrier of the Polkurier account, for choosing the available carriers.
 * Empty until the account is configured and reachable.
 */
readonly class AccountCarrier extends AbstractOptions
{
    public function __construct(
        private CarrierCatalog $carrierCatalog,
    ) {}

    public function toArray(): array
    {
        try {
            $carriers = $this->carrierCatalog->getAccountCarriers();
        } catch (PolkurierException) {
            return [];
        }
        $options = [];
        foreach ($carriers as $code => $carrier) {
            $options[$code] = CarrierNameProvider::nameOr($carrier->getName(), $code);
        }

        return $options;
    }
}
