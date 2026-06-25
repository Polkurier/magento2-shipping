<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data\Quote;

/**
 * Extension attribute for quote
 */
interface ShipmentDataInterface
{
    /**
     * @return int
     */
    public function getQuoteId(): int;

    /**
     * @param int $quoteId
     *
     * @return void
     */
    public function setQuoteId(int $quoteId): void;

    /**
     * @return string
     */
    public function getCarrierCode(): string;

    /**
     * @param string $carrierCode
     *
     * @return void
     */
    public function setCarrierCode(string $carrierCode): void;

    /**
     * @return string|null
     */
    public function getPointId(): ?string;

    /**
     * @param string|null $pointId
     *
     * @return void
     */
    public function setPointId(?string $pointId): void;
}
