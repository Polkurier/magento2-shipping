<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;
use Polkurier\Webservice\Enum\AddressTypeEnum;

abstract class AbstractAddress extends AbstractModel
{
    public const string FIELD_ADDRESS_ID = 'address_id';
    public const string FIELD_TYPE = 'type';
    public const string FIELD_PERSON = 'person';
    public const string FIELD_COMPANY = 'company';
    public const string FIELD_STREET = 'street';
    public const string FIELD_CITY = 'city';
    public const string FIELD_POSTCODE = 'postcode';
    public const string FIELD_COUNTRY = 'country';
    public const string FIELD_POINT_ID = 'point_id';
    public const string FIELD_EMAIL = 'email';
    public const string FIELD_PHONE = 'phone';

    /**
     * @return int|null
     */
    public function getAddressId(): ?int
    {
        return $this->getData(static::FIELD_ADDRESS_ID);
    }

    /**
     * @param int|null $addressId
     *
     * @return void
     */
    public function setAddressId(?int $addressId): void
    {
        $this->setData(static::FIELD_ADDRESS_ID, $addressId);
    }

    /**
     * @return AddressTypeEnum
     */
    public function getType(): AddressTypeEnum
    {
        return AddressTypeEnum::from($this->getData(static::FIELD_TYPE));
    }

    /**
     * @param AddressTypeEnum $type
     *
     * @return void
     */
    public function setType(AddressTypeEnum $type): void
    {
        $this->setData(static::FIELD_TYPE, $type->value);
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
