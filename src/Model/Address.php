<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ExternalEntityInterface;
use Polkurier\Shipping\Model\ResourceModel\Address as AddressResource;
use Polkurier\Webservice\Enum\AddressTypeEnum;
use Polkurier\Webservice\Model\Entity\Address as AddressSdk;
use Polkurier\Webservice\Model\Entity\BaseAddress;

class Address extends AbstractModel implements AddressInterface, ExternalEntityInterface
{
    public const string FIELD_ADDRESS_ID = 'address_id';
    public const string FIELD_EXT_ID = 'ext_id';
    public const string FIELD_CREATED_AT = 'created_at';
    public const string FIELD_UPDATED_AT = 'updated_at';
    public const string FIELD_NAME = 'name';
    public const string FIELD_TYPE = 'type';
    public const string FIELD_PERSON = 'person';
    public const string FIELD_COMPANY = 'company';
    public const string FIELD_STREET = 'street';
    public const string FIELD_HOUSE_NUMBER = 'house_number';
    public const string FIELD_FLAT_NUMBER = 'flat_number';
    public const string FIELD_CITY = 'city';
    public const string FIELD_POSTCODE = 'postcode';
    public const string FIELD_PROVINCE = 'province';
    public const string FIELD_COUNTRY = 'country';
    public const string FIELD_POINT_ID = 'point_id';
    public const string FIELD_EMAIL = 'email';
    public const string FIELD_PHONE = 'phone';
    public const string FIELD_DEFAULT = 'default';
    public const string FIELD_SHARED = 'shared';
    public const string FIELD_COVER = 'cover';

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
     * @return int|null
     */
    public function getAddressId(): ?int
    {
        return $this->getData(static::FIELD_ADDRESS_ID);
    }

    /**
     * @param int|null $id
     *
     * @return void
     */
    public function setAddressId(?int $id): void
    {
        $this->setData(static::FIELD_ADDRESS_ID, $id);
    }

    /**
     * @return string|null
     */
    public function getExtId(): ?string
    {
        return $this->getData(static::FIELD_EXT_ID);
    }

    /**
     * @param string|null $extId
     *
     * @return void
     */
    public function setExtId(?string $extId): void
    {
        $this->setData(static::FIELD_EXT_ID, $extId);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_CREATED_AT);
    }

    /**
     * @param \DateTimeInterface $createdAt
     *
     * @return void
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->setData(static::FIELD_CREATED_AT, $createdAt);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_UPDATED_AT);
    }

    /**
     * @param \DateTimeInterface $updatedAt
     *
     * @return void
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->setData(static::FIELD_UPDATED_AT, $updatedAt);
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
     * @return AddressTypeEnum
     */
    public function getType(): AddressTypeEnum
    {
        return $this->getData(static::FIELD_TYPE);
    }

    /**
     * @param AddressTypeEnum $type
     *
     * @return void
     */
    public function setType(AddressTypeEnum $type): void
    {
        $this->setData(static::FIELD_TYPE, $type);
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->getData(static::FIELD_DEFAULT);
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
        return $this->getData(static::FIELD_SHARED);
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
        return $this->getData(static::FIELD_COVER);
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
    public function getPerson(): string
    {
        return $this->getData(static::FIELD_PERSON);
    }

    /**
     * @param string $person
     *
     * @return void
     */
    public function setPerson(string $person): void
    {
        $this->setData(static::FIELD_PERSON, $person);
    }

    /**
     * @return string|null
     */
    public function getCompany(): ?string
    {
        return $this->getData(static::FIELD_COMPANY);
    }

    /**
     * @param string|null $company
     *
     * @return void
     */
    public function setCompany(?string $company): void
    {
        $this->setData(static::FIELD_COMPANY, $company);
    }

    /**
     * @return string
     */
    public function getStreet(): string
    {
        return $this->getData(static::FIELD_STREET);
    }

    /**
     * @param string $street
     *
     * @return void
     */
    public function setStreet(string $street): void
    {
        $this->setData(static::FIELD_STREET, $street);
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
     * @return string
     */
    public function getCity(): string
    {
        return $this->getData(static::FIELD_CITY);
    }

    /**
     * @param string $city
     *
     * @return void
     */
    public function setCity(string $city): void
    {
        $this->setData(static::FIELD_CITY, $city);
    }

    /**
     * @return string
     */
    public function getPostcode(): string
    {
        return $this->getData(static::FIELD_POSTCODE);
    }

    /**
     * @param string $postcode
     *
     * @return void
     */
    public function setPostcode(string $postcode): void
    {
        $this->setData(static::FIELD_POSTCODE, $postcode);
    }

    /**
     * @return string
     */
    public function getProvince(): string
    {
        return $this->getData(static::FIELD_PROVINCE);
    }

    /**
     * @param string $province
     *
     * @return void
     */
    public function setProvince(string $province): void
    {
        $this->setData(static::FIELD_PROVINCE, $province);
    }

    /**
     * @return string
     */
    public function getCountry(): string
    {
        return $this->getData(static::FIELD_COUNTRY);
    }

    /**
     * @param string $country
     *
     * @return void
     */
    public function setCountry(string $country): void
    {
        $this->setData(static::FIELD_COUNTRY, $country);
    }

    /**
     * @return string|null
     */
    public function getPointId(): ?string
    {
        return $this->getData(static::FIELD_POINT_ID);
    }

    /**
     * @param string|null $pointId
     *
     * @return void
     */
    public function setPointId(?string $pointId): void
    {
        $this->setData(static::FIELD_POINT_ID, $pointId);
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->getData(static::FIELD_EMAIL);
    }

    /**
     * @param string $email
     *
     * @return void
     */
    public function setEmail(string $email): void
    {
        $this->setData(static::FIELD_EMAIL, $email);
    }

    /**
     * @return string
     */
    public function getPhone(): string
    {
        return $this->getData(static::FIELD_PHONE);
    }

    /**
     * @param string $phone
     *
     * @return void
     */
    public function setPhone(string $phone): void
    {
        $this->setData(static::FIELD_PHONE, $phone);
    }
}
