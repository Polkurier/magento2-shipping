<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * An entry of the Polkurier address book: a sender address, or a cover address for returns.
 *
 * @api
 */
interface AddressInterface extends SyncableInterface
{
    public const string TYPE_SENDER = 'SENDER';
    public const string TYPE_RECIPIENT = 'RECIPIENT';

    public const string NAME = 'name';
    public const string TYPE = 'type';
    public const string PERSON = 'person';
    public const string COMPANY = 'company';
    public const string STREET = 'street';
    public const string HOUSE_NUMBER = 'house_number';
    public const string FLAT_NUMBER = 'flat_number';
    public const string CITY = 'city';
    public const string POSTCODE = 'postcode';
    public const string PROVINCE = 'province';
    public const string COUNTRY = 'country';
    public const string POINT_ID = 'point_id';
    public const string EMAIL = 'email';
    public const string PHONE = 'phone';
    public const string IS_DEFAULT = 'is_default';
    public const string IS_COVER = 'is_cover';
    public const string SHARED = 'shared';

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName(string $name): static;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType(string $type): static;

    /**
     * @return string
     */
    public function getPerson(): string;

    /**
     * @param string $person
     *
     * @return $this
     */
    public function setPerson(string $person): static;

    /**
     * @return string|null
     */
    public function getCompany(): ?string;

    /**
     * @param string|null $company
     *
     * @return $this
     */
    public function setCompany(?string $company): static;

    /**
     * @return string
     */
    public function getStreet(): string;

    /**
     * @param string $street
     *
     * @return $this
     */
    public function setStreet(string $street): static;

    /**
     * @return string
     */
    public function getHouseNumber(): string;

    /**
     * @param string $houseNumber
     *
     * @return $this
     */
    public function setHouseNumber(string $houseNumber): static;

    /**
     * @return string|null
     */
    public function getFlatNumber(): ?string;

    /**
     * @param string|null $flatNumber
     *
     * @return $this
     */
    public function setFlatNumber(?string $flatNumber): static;

    /**
     * @return string
     */
    public function getCity(): string;

    /**
     * @param string $city
     *
     * @return $this
     */
    public function setCity(string $city): static;

    /**
     * @return string
     */
    public function getPostcode(): string;

    /**
     * @param string $postcode
     *
     * @return $this
     */
    public function setPostcode(string $postcode): static;

    /**
     * @return string|null
     */
    public function getProvince(): ?string;

    /**
     * @param string|null $province
     *
     * @return $this
     */
    public function setProvince(?string $province): static;

    /**
     * @return string
     */
    public function getCountry(): string;

    /**
     * @param string $country
     *
     * @return $this
     */
    public function setCountry(string $country): static;

    /**
     * @return string|null
     */
    public function getPointId(): ?string;

    /**
     * @param string|null $pointId
     *
     * @return $this
     */
    public function setPointId(?string $pointId): static;

    /**
     * @return string
     */
    public function getEmail(): string;

    /**
     * @param string $email
     *
     * @return $this
     */
    public function setEmail(string $email): static;

    /**
     * @return string
     */
    public function getPhone(): string;

    /**
     * @param string $phone
     *
     * @return $this
     */
    public function setPhone(string $phone): static;

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

    /**
     * @return bool
     */
    public function isCover(): bool;

    /**
     * @param bool $cover
     *
     * @return $this
     */
    public function setCover(bool $cover): static;

    /**
     * @return bool
     */
    public function isShared(): bool;

    /**
     * @param bool $shared
     *
     * @return $this
     */
    public function setShared(bool $shared): static;
}
