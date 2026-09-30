<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Checkout;

/**
 * The pickup points posted with the shipping methods of the multi-address checkout, for the rest
 * of the request: the controller receives them, the checkout model stores them.
 */
class MultishippingPoints
{
    /**
     * @var array<int, string> point ids by quote address id
     */
    private array $points = [];

    /**
     * @param array<int|string, mixed> $points as posted, by quote address id
     */
    public function setPosted(array $points): void
    {
        $this->points = [];
        foreach ($points as $addressId => $pointId) {
            if (\is_scalar($pointId)) {
                $this->points[(int)$addressId] = (string)$pointId;
            }
        }
    }

    public function get(int $quoteAddressId): ?string
    {
        return $this->points[$quoteAddressId] ?? null;
    }
}
