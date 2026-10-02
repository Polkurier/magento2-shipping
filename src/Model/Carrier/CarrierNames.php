<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

/**
 * Carriers by name, as the admin shows them: the name Polkurier gives a carrier, kept with its
 * settings. Its code is for the API and for developers; it's shown only for a carrier the module
 * knows nothing else of.
 */
class CarrierNames
{
    public function __construct(
        private readonly CarrierConfigs $carrierConfigs,
    ) {}

    public function get(string $carrierCode): string
    {
        $name = $this->carrierConfigs->get($carrierCode)?->getCarrierName();

        return $name !== null && $name !== '' ? $name : $carrierCode;
    }

    /**
     * @return array<string, string> names by carrier code, of every carrier the module has settings for
     */
    public function getAll(): array
    {
        $names = [];
        foreach ($this->carrierConfigs->getAll() as $code => $config) {
            $names[$code] = $config->getCarrierName() !== '' ? $config->getCarrierName() : $code;
        }
        \asort($names, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $names;
    }
}
