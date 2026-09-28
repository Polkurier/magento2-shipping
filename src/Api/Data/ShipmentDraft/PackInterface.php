<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data\ShipmentDraft;

/**
 * One parcel of a shipment draft.
 */
interface PackInterface
{
    public const string TEMPLATE_ID = 'template_id';
    public const string PACK_TYPE = 'pack_type';
    public const string LENGTH = 'length';
    public const string WIDTH = 'width';
    public const string HEIGHT = 'height';
    public const string WEIGHT = 'weight';
    public const string AMOUNT = 'amount';

    /**
     * @return int|null
     */
    public function getTemplateId(): ?int;

    /**
     * @param int|null $templateId
     * @return $this
     */
    public function setTemplateId(?int $templateId): static;

    /**
     * @return string
     */
    public function getPackType(): string;

    /**
     * @param string $packType
     * @return $this
     */
    public function setPackType(string $packType): static;

    /**
     * @return int
     */
    public function getLength(): int;

    /**
     * @param int $length
     * @return $this
     */
    public function setLength(int $length): static;

    /**
     * @return int
     */
    public function getWidth(): int;

    /**
     * @param int $width
     * @return $this
     */
    public function setWidth(int $width): static;

    /**
     * @return int
     */
    public function getHeight(): int;

    /**
     * @param int $height
     * @return $this
     */
    public function setHeight(int $height): static;

    /**
     * @return float
     */
    public function getWeight(): float;

    /**
     * @param float $weight
     * @return $this
     */
    public function setWeight(float $weight): static;

    /**
     * @return int
     */
    public function getAmount(): int;

    /**
     * @param int $amount
     * @return $this
     */
    public function setAmount(int $amount): static;
}
