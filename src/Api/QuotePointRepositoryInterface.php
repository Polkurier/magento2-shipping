<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Polkurier\Shipping\Api\Data\QuotePointInterface;

/**
 * Stores the pickup point chosen at checkout, per quote, or per shipping address in multi-address
 * checkout.
 *
 * @api
 */
interface QuotePointRepositoryInterface
{
    /**
     * @param int $quoteId
     * @param int $quoteAddressId the shipping address in multi-address checkout, else 0
     *
     * @return \Polkurier\Shipping\Api\Data\QuotePointInterface|null null when none is chosen
     */
    public function getForQuote(int $quoteId, int $quoteAddressId = 0): ?QuotePointInterface;

    /**
     * @param \Polkurier\Shipping\Api\Data\QuotePointInterface $point
     *
     * @return void
     */
    public function save(QuotePointInterface $point): void;

    /**
     * @param int $quoteId
     * @param int $quoteAddressId the shipping address in multi-address checkout, else 0
     *
     * @return void
     */
    public function deleteForQuote(int $quoteId, int $quoteAddressId = 0): void;
}
