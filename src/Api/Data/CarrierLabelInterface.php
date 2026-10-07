<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * The name a carrier is shown under in one store view, in place of the carrier's default label.
 *
 * @api
 */
interface CarrierLabelInterface
{
    public const string FIELD_STORE_ID = 'store_id';
    public const string FIELD_LABEL = 'label';

    /**
     * @return int
     */
    public function getStoreId(): int;

    /**
     * @param int $storeId
     *
     * @return $this
     */
    public function setStoreId(int $storeId): static;

    /**
     * @return string
     */
    public function getLabel(): string;

    /**
     * @param string $label
     *
     * @return $this
     */
    public function setLabel(string $label): static;
}
