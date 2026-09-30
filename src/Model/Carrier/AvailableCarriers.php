<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Polkurier\Shipping\Model\Config;

/**
 * The carriers the merchant works with, from the Available carriers setting: the one rule behind
 * every list that offers carriers. Nothing selected means all of the account's carriers.
 *
 * It applies both to the account's carrier list and to the carrier settings kept in Magento,
 * since checkout still offers domestic carriers from their settings when the list can't be read.
 */
class AvailableCarriers
{
    public function __construct(
        private readonly Config $config,
    ) {}

    /**
     * @return string[]|null the carrier codes, or null when every carrier is available
     */
    public function getCodes(): ?array
    {
        $codes = $this->config->getAvailableCarriers();

        return $codes === [] ? null : $codes;
    }

    public function isAvailable(string $carrierCode): bool
    {
        $codes = $this->getCodes();

        return $codes === null || \in_array($carrierCode, $codes, true);
    }

    /**
     * @template T
     * @param array<string, T> $byCode keyed by carrier code
     * @return array<string, T>
     */
    public function filter(array $byCode): array
    {
        $codes = $this->getCodes();

        return $codes === null ? $byCode : \array_intersect_key($byCode, \array_flip($codes));
    }
}
