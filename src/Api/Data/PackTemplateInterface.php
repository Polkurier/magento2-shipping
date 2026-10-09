<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * A saved parcel: dimensions, weight and optionally default services.
 *
 * @api
 */
interface PackTemplateInterface extends SyncableInterface
{
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_PACK_TYPE = 'pack_type';
    public const string FIELD_LENGTH = 'length';
    public const string FIELD_WIDTH = 'width';
    public const string FIELD_HEIGHT = 'height';
    public const string FIELD_WEIGHT = 'weight';

    /**
     * The weight of the packaging itself, added to the contents of a parcel weighed by its
     * contents. Kept in Magento only: Polkurier's pack templates have no such field.
     */
    public const string FIELD_TARE = 'tare';

    public const string FIELD_COD_AMOUNT = 'cod_amount';
    public const string FIELD_INSURANCE_AMOUNT = 'insurance_amount';
    public const string FIELD_CARRIER_CODE = 'carrier_code';
    public const string FIELD_SERVICES = 'services';
    public const string FIELD_IS_DEFAULT = 'is_default';

    /**
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * @param string|null $description
     *
     * @return $this
     */
    public function setDescription(?string $description): static;

    /**
     * The shipment type given by the template's parcel type, e.g. "envelope" for an envelope.
     *
     * @return string
     */
    public function getShipmentType(): string;

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
     * The most a parcel of this template may weigh, in kilograms.
     *
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
     * What the packaging itself weighs, in kilograms; null when not set.
     *
     * @return float|null
     */
    public function getTare(): ?float;

    /**
     * @param float|null $tare
     *
     * @return $this
     */
    public function setTare(?float $tare): static;

    /**
     * @return float
     */
    public function getCodAmount(): float;

    /**
     * @param float $codAmount
     *
     * @return $this
     */
    public function setCodAmount(float $codAmount): static;

    /**
     * @return float
     */
    public function getInsuranceAmount(): float;

    /**
     * @param float $insuranceAmount
     *
     * @return $this
     */
    public function setInsuranceAmount(float $insuranceAmount): static;

    /**
     * @return string|null
     */
    public function getCarrierCode(): ?string;

    /**
     * @param string|null $carrierCode
     *
     * @return $this
     */
    public function setCarrierCode(?string $carrierCode): static;

    /**
     * @return string[]
     */
    public function getServices(): array;

    /**
     * @param string[] $services
     *
     * @return $this
     */
    public function setServices(array $services): static;

    /**
     * @return bool
     */
    public function isDefault(): bool;

    /**
     * @param bool $default
     *
     * @return $this
     */
    public function setDefault(bool $default): static;
}
