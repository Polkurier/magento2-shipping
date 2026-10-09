<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;

/**
 * Carrier names as the admin shows them: the name Polkurier gives a carrier, kept with its
 * settings. A carrier's code is for the API and for developers; it's shown only for a carrier the
 * module knows nothing else about.
 */
readonly class CarrierNameProvider
{
    public function __construct(
        private CarrierConfigProvider $carrierConfigProvider,
    ) {}

    public function get(string $carrierCode): string
    {
        return self::nameOr((string)$this->carrierConfigProvider->get($carrierCode)?->getCarrierName(), $carrierCode);
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
        foreach ($this->carrierConfigProvider->getAll() as $code => $config) {
            $names[$code] = self::nameOr($config->getCarrierName(), $code);
        }
        \asort($names, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $names;
    }
}
