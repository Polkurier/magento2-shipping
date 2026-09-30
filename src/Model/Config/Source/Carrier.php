<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The carriers the merchant works with, from the Polkurier account.
 * Empty until the account is configured and reachable.
 */
class Carrier extends AbstractOptions
{
    public function __construct(
        protected readonly CarrierCatalog $catalog,
    ) {}

    public function toArray(): array
    {
        try {
            $carriers = $this->loadCarriers();
        } catch (PolkurierException) {
            return ['' => (string)\__('-- Configure the Polkurier account first --')];
        }
        $options = ['' => (string)\__('-- None --')];
        foreach ($carriers as $code => $carrier) {
            $options[$code] = \sprintf('%s (%s)', $carrier->getName(), $code);
        }

        return $options;
    }

    /**
     * @return array<string, \Polkurier\Webservice\Response\Carrier>
     * @throws PolkurierException
     */
    protected function loadCarriers(): array
    {
        return $this->catalog->getCarriers();
    }
}
