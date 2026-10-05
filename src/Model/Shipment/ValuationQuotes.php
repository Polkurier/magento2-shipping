<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

/**
 * Reads the prices out of Polkurier's valuation answer, which the SDK hands over as decoded JSON
 * because its shape isn't fixed.
 *
 * Polkurier answers with a list, one entry per carrier with its service code, net and gross price
 * and whether it can take the shipment. The SDK documents quotes keyed by carrier with price_net
 * and price_gross instead, and a single quote without a code is the draft's carrier. All three
 * are read; anything else is counted as unrecognised, for the caller to log.
 */
readonly class ValuationQuotes
{
    /**
     * @param array<array-key, mixed> $valuation
     * @param string $carrierCode the draft's carrier, for a quote that doesn't name one
     * @return array{quotes: array<string, array{net: float, gross: float}>, unrecognised: int}
     */
    public static function fromResponse(array $valuation, string $carrierCode): array
    {
        $entries = [];
        if (self::price($valuation) !== null) {
            $entries[] = [$carrierCode, $valuation];
        } else {
            foreach ($valuation as $key => $entry) {
                $code = \is_array($entry) && isset($entry['servicecode']) ? (string)$entry['servicecode'] : (\is_string($key) ? $key : null);
                $entries[] = [$code, $entry];
            }
        }

        $quotes = [];
        $unrecognised = 0;
        foreach ($entries as [$code, $entry]) {
            $price = \is_array($entry) ? self::price($entry) : null;
            if ($code === null || $code === '' || $price === null) {
                // An entry without prices is a carrier that can't price the draft; one we can't
                // read at all is what the caller should hear about.
                $unrecognised += \is_array($entry) && isset($entry['servicecode']) ? 0 : 1;
                continue;
            }
            if (($entry['available'] ?? true) !== false) {
                $quotes[$code] = $price;
            }
        }

        return ['quotes' => $quotes, 'unrecognised' => $unrecognised];
    }

    /**
     * @param array<array-key, mixed> $entry
     * @return array{net: float, gross: float}|null
     */
    private static function price(array $entry): ?array
    {
        foreach ([['netprice', 'grossprice'], ['price_net', 'price_gross']] as [$net, $gross]) {
            if (isset($entry[$net], $entry[$gross]) && \is_numeric($entry[$net]) && \is_numeric($entry[$gross])) {
                return ['net' => (float)$entry[$net], 'gross' => (float)$entry[$gross]];
            }
        }

        return null;
    }
}
