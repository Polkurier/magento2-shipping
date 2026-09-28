<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\AddressInterface;

/**
 * An address book entry kept in Magento and mirrored with Polkurier.
 */
class Address extends AbstractModel implements AddressInterface
{
    use SyncableTrait;

    public const string ID_FIELD = 'address_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_address';

    /**
     * @var string
     */
    protected $_eventObject = 'address';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Address::class);
    }

    public function getName(): string
    {
        $value = $this->getData(self::NAME);

        return (string)$value;
    }

    public function setName(string $name): static
    {
        return $this->setData(self::NAME, $name);
    }

    public function getType(): string
    {
        $value = $this->getData(self::TYPE);

        return (string)$value;
    }

    public function setType(string $type): static
    {
        return $this->setData(self::TYPE, $type);
    }

    public function getPerson(): string
    {
        $value = $this->getData(self::PERSON);

        return (string)$value;
    }

    public function setPerson(string $person): static
    {
        return $this->setData(self::PERSON, $person);
    }

    public function getCompany(): ?string
    {
        $value = $this->getData(self::COMPANY);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCompany(?string $company): static
    {
        return $this->setData(self::COMPANY, $company);
    }

    public function getStreet(): string
    {
        $value = $this->getData(self::STREET);

        return (string)$value;
    }

    public function setStreet(string $street): static
    {
        return $this->setData(self::STREET, $street);
    }

    public function getHouseNumber(): string
    {
        $value = $this->getData(self::HOUSE_NUMBER);

        return (string)$value;
    }

    public function setHouseNumber(string $houseNumber): static
    {
        return $this->setData(self::HOUSE_NUMBER, $houseNumber);
    }

    public function getFlatNumber(): ?string
    {
        $value = $this->getData(self::FLAT_NUMBER);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setFlatNumber(?string $flatNumber): static
    {
        return $this->setData(self::FLAT_NUMBER, $flatNumber);
    }

    public function getCity(): string
    {
        $value = $this->getData(self::CITY);

        return (string)$value;
    }

    public function setCity(string $city): static
    {
        return $this->setData(self::CITY, $city);
    }

    public function getPostcode(): string
    {
        $value = $this->getData(self::POSTCODE);

        return (string)$value;
    }

    public function setPostcode(string $postcode): static
    {
        return $this->setData(self::POSTCODE, $postcode);
    }

    public function getProvince(): ?string
    {
        $value = $this->getData(self::PROVINCE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setProvince(?string $province): static
    {
        return $this->setData(self::PROVINCE, $province);
    }

    public function getCountry(): string
    {
        $value = $this->getData(self::COUNTRY);

        return (string)$value;
    }

    public function setCountry(string $country): static
    {
        return $this->setData(self::COUNTRY, $country);
    }

    public function getPointId(): ?string
    {
        $value = $this->getData(self::POINT_ID);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPointId(?string $pointId): static
    {
        return $this->setData(self::POINT_ID, $pointId);
    }

    public function getEmail(): string
    {
        $value = $this->getData(self::EMAIL);

        return (string)$value;
    }

    public function setEmail(string $email): static
    {
        return $this->setData(self::EMAIL, $email);
    }

    public function getPhone(): string
    {
        $value = $this->getData(self::PHONE);

        return (string)$value;
    }

    public function setPhone(string $phone): static
    {
        return $this->setData(self::PHONE, $phone);
    }

    public function isDefault(): bool
    {
        $value = $this->getData(self::IS_DEFAULT);

        return (bool)$value;
    }

    public function setDefault(bool $default): static
    {
        return $this->setData(self::IS_DEFAULT, (int)$default);
    }

    public function isCover(): bool
    {
        $value = $this->getData(self::IS_COVER);

        return (bool)$value;
    }

    public function setCover(bool $cover): static
    {
        return $this->setData(self::IS_COVER, (int)$cover);
    }

    public function isShared(): bool
    {
        $value = $this->getData(self::SHARED);

        return (bool)$value;
    }

    public function setShared(bool $shared): static
    {
        return $this->setData(self::SHARED, (int)$shared);
    }
}
