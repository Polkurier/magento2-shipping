<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * A saved parcel: dimensions, weight and optionally default services.
 */
interface PackTemplateInterface extends SyncableInterface
{
    public const string NAME = 'name';
    public const string DESCRIPTION = 'description';
    public const string PACK_TYPE = 'pack_type';
    public const string LENGTH = 'length';
    public const string WIDTH = 'width';
    public const string HEIGHT = 'height';
    public const string WEIGHT = 'weight';
    /**
     * What the packaging itself weighs, added to what's in a parcel weighed by its contents. Kept
     * in Magento only: Polkurier's pack templates have no such thing.
     */
    public const string TARE = 'tare';
    public const string COD_AMOUNT = 'cod_amount';
    public const string INSURANCE_AMOUNT = 'insurance_amount';
    public const string CARRIER_CODE = 'carrier_code';
    public const string SERVICES = 'services';
    public const string IS_DEFAULT = 'is_default';

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): static;

    /**
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): static;

    /**
     * The shipment type its parcel type makes, e.g. "envelope" for an envelope.
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
     * The most a parcel of this template may weigh, in kilograms.
     *
     * @return float
     */
    public function getWeight(): float;

    /**
     * @param float $weight
     * @return $this
     */
    public function setWeight(float $weight): static;

    /**
     * What the packaging itself weighs, in kilograms; null when not said.
     *
     * @return float|null
     */
    public function getTare(): ?float;

    /**
     * @param float|null $tare
     * @return $this
     */
    public function setTare(?float $tare): static;

    /**
     * @return float
     */
    public function getCodAmount(): float;

    /**
     * @param float $codAmount
     * @return $this
     */
    public function setCodAmount(float $codAmount): static;

    /**
     * @return float
     */
    public function getInsuranceAmount(): float;

    /**
     * @param float $insuranceAmount
     * @return $this
     */
    public function setInsuranceAmount(float $insuranceAmount): static;

    /**
     * @return string|null
     */
    public function getCarrierCode(): ?string;

    /**
     * @param string|null $carrierCode
     * @return $this
     */
    public function setCarrierCode(?string $carrierCode): static;

    /**
     * @return string[]
     */
    public function getServices(): array;

    /**
     * @param string[] $services
     * @return $this
     */
    public function setServices(array $services): static;

    /**
     * @return bool
     */
    public function isDefault(): bool;

    /**
     * @param bool $default
     * @return $this
     */
    public function setDefault(bool $default): static;
}
