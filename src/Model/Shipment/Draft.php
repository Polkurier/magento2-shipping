<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\DataObject;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;

/**
 * What a merchant asks Polkurier to ship; see the interface.
 */
class Draft extends DataObject implements ShipmentDraftInterface
{
    public function getCarrierCode(): string
    {
        $value = $this->getData(self::CARRIER_CODE);

        return (string)$value;
    }

    public function setCarrierCode(string $carrierCode): static
    {
        return $this->setData(self::CARRIER_CODE, $carrierCode);
    }

    public function getShipmentType(): string
    {
        $value = $this->getData(self::SHIPMENT_TYPE);

        return (string)$value;
    }

    public function setShipmentType(string $shipmentType): static
    {
        return $this->setData(self::SHIPMENT_TYPE, $shipmentType);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData(self::DESCRIPTION);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setDescription(?string $description): static
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function getSenderAddressId(): ?int
    {
        $value = $this->getData(self::SENDER_ADDRESS_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setSenderAddressId(?int $senderAddressId): static
    {
        return $this->setData(self::SENDER_ADDRESS_ID, $senderAddressId);
    }

    public function getRecipientPerson(): string
    {
        $value = $this->getData(self::RECIPIENT_PERSON);

        return (string)$value;
    }

    public function setRecipientPerson(string $recipientPerson): static
    {
        return $this->setData(self::RECIPIENT_PERSON, $recipientPerson);
    }

    public function getRecipientCompany(): ?string
    {
        $value = $this->getData(self::RECIPIENT_COMPANY);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientCompany(?string $recipientCompany): static
    {
        return $this->setData(self::RECIPIENT_COMPANY, $recipientCompany);
    }

    public function getRecipientStreet(): string
    {
        $value = $this->getData(self::RECIPIENT_STREET);

        return (string)$value;
    }

    public function setRecipientStreet(string $recipientStreet): static
    {
        return $this->setData(self::RECIPIENT_STREET, $recipientStreet);
    }

    public function getRecipientHouseNumber(): ?string
    {
        $value = $this->getData(self::RECIPIENT_HOUSE_NUMBER);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientHouseNumber(?string $recipientHouseNumber): static
    {
        return $this->setData(self::RECIPIENT_HOUSE_NUMBER, $recipientHouseNumber);
    }

    public function getRecipientFlatNumber(): ?string
    {
        $value = $this->getData(self::RECIPIENT_FLAT_NUMBER);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientFlatNumber(?string $recipientFlatNumber): static
    {
        return $this->setData(self::RECIPIENT_FLAT_NUMBER, $recipientFlatNumber);
    }

    public function getRecipientPostcode(): string
    {
        $value = $this->getData(self::RECIPIENT_POSTCODE);

        return (string)$value;
    }

    public function setRecipientPostcode(string $recipientPostcode): static
    {
        return $this->setData(self::RECIPIENT_POSTCODE, $recipientPostcode);
    }

    public function getRecipientCity(): string
    {
        $value = $this->getData(self::RECIPIENT_CITY);

        return (string)$value;
    }

    public function setRecipientCity(string $recipientCity): static
    {
        return $this->setData(self::RECIPIENT_CITY, $recipientCity);
    }

    public function getRecipientCountry(): string
    {
        $value = $this->getData(self::RECIPIENT_COUNTRY);

        return (string)$value;
    }

    public function setRecipientCountry(string $recipientCountry): static
    {
        return $this->setData(self::RECIPIENT_COUNTRY, $recipientCountry);
    }

    public function getRecipientPhone(): string
    {
        $value = $this->getData(self::RECIPIENT_PHONE);

        return (string)$value;
    }

    public function setRecipientPhone(string $recipientPhone): static
    {
        return $this->setData(self::RECIPIENT_PHONE, $recipientPhone);
    }

    public function getRecipientEmail(): string
    {
        $value = $this->getData(self::RECIPIENT_EMAIL);

        return (string)$value;
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        return $this->setData(self::RECIPIENT_EMAIL, $recipientEmail);
    }

    public function getRecipientPointId(): ?string
    {
        $value = $this->getData(self::RECIPIENT_POINT_ID);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientPointId(?string $recipientPointId): static
    {
        return $this->setData(self::RECIPIENT_POINT_ID, $recipientPointId);
    }

    public function getPacks(): array
    {
        $value = $this->getData(self::PACKS);

        return \is_array($value) ? $value : [];
    }

    public function setPacks(array $packs): static
    {
        return $this->setData(self::PACKS, $packs);
    }

    public function getItems(): array
    {
        $value = $this->getData(self::ITEMS);

        return \is_array($value) ? $value : [];
    }

    public function setItems(array $items): static
    {
        return $this->setData(self::ITEMS, $items);
    }

    public function isPacksEstimated(): bool
    {
        return (bool)$this->getData(self::PACKS_ESTIMATED);
    }

    public function setPacksEstimated(bool $estimated): static
    {
        return $this->setData(self::PACKS_ESTIMATED, $estimated);
    }

    public function getCodAmount(): ?float
    {
        $value = $this->getData(self::COD_AMOUNT);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setCodAmount(?float $codAmount): static
    {
        return $this->setData(self::COD_AMOUNT, $codAmount);
    }

    public function getCodType(): ?string
    {
        $value = $this->getData(self::COD_TYPE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCodType(?string $codType): static
    {
        return $this->setData(self::COD_TYPE, $codType);
    }

    public function getCodReturnType(): ?string
    {
        $value = $this->getData(self::COD_RETURN_TYPE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCodReturnType(?string $codReturnType): static
    {
        return $this->setData(self::COD_RETURN_TYPE, $codReturnType);
    }

    public function getCodBankAccountId(): ?int
    {
        $value = $this->getData(self::COD_BANK_ACCOUNT_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setCodBankAccountId(?int $codBankAccountId): static
    {
        return $this->setData(self::COD_BANK_ACCOUNT_ID, $codBankAccountId);
    }

    public function getInsuranceAmount(): ?float
    {
        $value = $this->getData(self::INSURANCE_AMOUNT);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setInsuranceAmount(?float $insuranceAmount): static
    {
        return $this->setData(self::INSURANCE_AMOUNT, $insuranceAmount);
    }

    public function getCoverAddressId(): ?int
    {
        $value = $this->getData(self::COVER_ADDRESS_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setCoverAddressId(?int $coverAddressId): static
    {
        return $this->setData(self::COVER_ADDRESS_ID, $coverAddressId);
    }

    public function getServices(): array
    {
        $value = $this->getData(self::SERVICES);

        return \is_array($value) ? $value : [];
    }

    public function setServices(array $services): static
    {
        return $this->setData(self::SERVICES, $services);
    }

    public function getPickupMode(): string
    {
        $value = $this->getData(self::PICKUP_MODE);

        return (string)$value;
    }

    public function setPickupMode(string $pickupMode): static
    {
        return $this->setData(self::PICKUP_MODE, $pickupMode);
    }

    public function getPickupDate(): ?string
    {
        $value = $this->getData(self::PICKUP_DATE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPickupDate(?string $pickupDate): static
    {
        return $this->setData(self::PICKUP_DATE, $pickupDate);
    }

    public function getPickupTimeFrom(): ?string
    {
        $value = $this->getData(self::PICKUP_TIME_FROM);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPickupTimeFrom(?string $pickupTimeFrom): static
    {
        return $this->setData(self::PICKUP_TIME_FROM, $pickupTimeFrom);
    }

    public function getPickupTimeTo(): ?string
    {
        $value = $this->getData(self::PICKUP_TIME_TO);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPickupTimeTo(?string $pickupTimeTo): static
    {
        return $this->setData(self::PICKUP_TIME_TO, $pickupTimeTo);
    }

    public function getAdditionalFields(): array
    {
        $value = $this->getData(self::ADDITIONAL_FIELDS);

        return \is_array($value) ? $value : [];
    }

    public function setAdditionalFields(array $additionalFields): static
    {
        return $this->setData(self::ADDITIONAL_FIELDS, $additionalFields);
    }
}
