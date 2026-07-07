<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ExternalEntityInterface;
use Polkurier\Shipping\Model\ResourceModel\Address as AddressResource;
use Polkurier\Webservice\Model\Entity\Address as AddressSdk;
use Polkurier\Webservice\Model\Entity\BaseAddress;

class Address extends AbstractAddress implements AddressInterface, ExternalEntityInterface
{
    use ExternalEntityTrait;

    public const string FIELD_NAME = 'name';
    public const string FIELD_HOUSE_NUMBER = 'house_number';
    public const string FIELD_FLAT_NUMBER = 'flat_number';
    public const string FIELD_PROVINCE = 'province';
    public const string FIELD_DEFAULT = 'default';
    public const string FIELD_SHARED = 'shared';
    public const string FIELD_COVER = 'cover';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_address';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_address';

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(AddressResource::class);
        $this->setCountry(BaseAddress::DEFAULT_COUNTRY);
        $this->setType(AddressSdk::DEFAULT_TYPE);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->getData(static::FIELD_NAME);
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->setData(static::FIELD_NAME, $name);
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return (bool)$this->getData(static::FIELD_DEFAULT);
    }

    /**
     * @param bool $default
     *
     * @return void
     */
    public function setDefault(bool $default): void
    {
        $this->setData(static::FIELD_DEFAULT, $default);
    }

    /**
     * @return bool
     */
    public function isShared(): bool
    {
        return (bool)$this->getData(static::FIELD_SHARED);
    }

    /**
     * @param bool $shared
     *
     * @return void
     */
    public function setShared(bool $shared): void
    {
        $this->setData(static::FIELD_SHARED, $shared);
    }

    /**
     * @return bool
     */
    public function isCoverAddress(): bool
    {
        return (bool)$this->getData(static::FIELD_COVER);
    }

    /**
     * @param bool $coverAddress
     *
     * @return void
     */
    public function setCoverAddress(bool $coverAddress): void
    {
        $this->setData(static::FIELD_COVER, $coverAddress);
    }

    /**
     * @return string
     */
    public function getHouseNumber(): string
    {
        return $this->getData(static::FIELD_HOUSE_NUMBER);
    }

    /**
     * @param string $houseNumber
     *
     * @return void
     */
    public function setHouseNumber(string $houseNumber): void
    {
        $this->setData(static::FIELD_HOUSE_NUMBER, $houseNumber);
    }

    /**
     * @return string
     */
    public function getFlatNumber(): string
    {
        return $this->getData(static::FIELD_FLAT_NUMBER);
    }

    /**
     * @param string $flatNumber
     *
     * @return void
     */
    public function setFlatNumber(string $flatNumber): void
    {
        $this->setData(static::FIELD_FLAT_NUMBER, $flatNumber);
    }

    /**
     * @return string|null
     */
    public function getProvince(): ?string
    {
        return $this->getData(static::FIELD_PROVINCE);
    }

    /**
     * @param string|null $province
     *
     * @return void
     */
    public function setProvince(?string $province): void
    {
        $this->setData(static::FIELD_PROVINCE, $province);
    }
}
