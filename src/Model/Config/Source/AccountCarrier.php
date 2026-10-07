<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
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
        private CarrierCatalog $catalog,
    ) {}

    public function toArray(): array
    {
        try {
            $carriers = $this->catalog->getAccountCarriers();
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
