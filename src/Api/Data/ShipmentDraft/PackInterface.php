<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data\ShipmentDraft;

/**
 * One parcel of a shipment draft.
 *
 * @api
 */
interface PackInterface
{
    public const string FIELD_TEMPLATE_ID = 'template_id';
    public const string FIELD_PACK_TYPE = 'pack_type';
    public const string FIELD_LENGTH = 'length';
    public const string FIELD_WIDTH = 'width';
    public const string FIELD_HEIGHT = 'height';
    public const string FIELD_WEIGHT = 'weight';
    public const string FIELD_AMOUNT = 'amount';
    public const string FIELD_ITEMS = 'items';

    /**
     * The name to save a parcel without a template under as a pack template once the shipment is
     * placed; empty to save nothing.
     */
    public const string FIELD_KEEP_AS = 'keep_as';

    /**
     * @return int|null
     */
    public function getTemplateId(): ?int;

    /**
     * @param int|null $templateId
     *
     * @return $this
     */
    public function setTemplateId(?int $templateId): static;

    /**
     * @return string
     */
    public function getPackType(): string;

    /**
     * @param string $packType
     *
     * @return $this
     */
    public function setPackType(string $packType): static;

    /**
     * @return int
     */
    public function getLength(): int;

    /**
     * @param int $length
     *
     * @return $this
     */
    public function setLength(int $length): static;

    /**
     * @return int
     */
    public function getWidth(): int;

    /**
     * @param int $width
     *
     * @return $this
     */
    public function setWidth(int $width): static;

    /**
     * @return int
     */
    public function getHeight(): int;

    /**
     * @param int $height
     *
     * @return $this
     */
    public function setHeight(int $height): static;

    /**
     * @return float
     */
    public function getWeight(): float;

    /**
     * @param float $weight
     *
     * @return $this
     */
    public function setWeight(float $weight): static;

    /**
     * @return int
     */
    public function getAmount(): int;

    /**
     * @param int $amount
     *
     * @return $this
     */
    public function setAmount(int $amount): static;

    /**
     * The order's items in one parcel of these; empty when not said.
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface[]
     */
    public function getItems(): array;

    /**
     * @param \Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items): static;

    public function getKeepAs(): string;

    public function setKeepAs(string $name): static;
}
