<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * The pickup point chosen in checkout, held on the quote (or one of its addresses, in multi-address
 * checkout) until the order is placed.
 *
 * @api
 */
interface QuotePointInterface
{
    public const string FIELD_QUOTE_ID = 'quote_id';
    public const string FIELD_QUOTE_ADDRESS_ID = 'quote_address_id';
    public const string FIELD_METHOD_CODE = 'method_code';
    public const string FIELD_POINT_ID = 'point_id';
    public const string FIELD_POINT_LABEL = 'point_label';
    public const string FIELD_POINT_COD = 'point_cod';

    /**
     * @return int
     */
    public function getQuoteId(): int;

    /**
     * @param int $quoteId
     *
     * @return $this
     */
    public function setQuoteId(int $quoteId): static;

    /**
     * @return int the shipping address in multi-address checkout, else 0
     */
    public function getQuoteAddressId(): int;

    /**
     * @param int $quoteAddressId
     *
     * @return $this
     */
    public function setQuoteAddressId(int $quoteAddressId): static;

    /**
     * @return string
     */
    public function getMethodCode(): string;

    /**
     * @param string $methodCode
     *
     * @return $this
     */
    public function setMethodCode(string $methodCode): static;

    /**
     * @return string
     */
    public function getPointId(): string;

    /**
     * @param string $pointId
     *
     * @return $this
     */
    public function setPointId(string $pointId): static;

    /**
     * @return string|null
     */
    public function getPointLabel(): ?string;

    /**
     * @param string|null $pointLabel
     *
     * @return $this
     */
    public function setPointLabel(?string $pointLabel): static;

    /**
     * Whether the point accepts cash on delivery; null when that isn't known, as when Polkurier
     * couldn't be asked: then it's taken to.
     *
     * @return bool|null
     */
    public function getPointCod(): ?bool;

    /**
     * @param bool|null $pointCod null when not known
     *
     * @return $this
     */
    public function setPointCod(?bool $pointCod): static;
}
