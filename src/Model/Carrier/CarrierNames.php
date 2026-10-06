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
readonly class CarrierNames
{
    public function __construct(
        private CarrierConfigs $carrierConfigs,
    ) {}

    public function get(string $carrierCode): string
    {
        return self::nameOr((string)$this->carrierConfigs->get($carrierCode)?->getCarrierName(), $carrierCode);
    }

    /**
     * A carrier's name, or its code when it has none.
     */
    public static function nameOr(string $name, string|int $carrierCode): string
    {
        return $name !== '' ? $name : (string)$carrierCode;
    }

    /**
     * @return array<string, string> names by carrier code, of every carrier the module has settings for
     */
    public function getAll(): array
    {
        $names = [];
        foreach ($this->carrierConfigs->getAll() as $code => $config) {
            $names[$code] = self::nameOr($config->getCarrierName(), $code);
        }
        \asort($names, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $names;
    }
}
