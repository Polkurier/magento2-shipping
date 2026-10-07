<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\DataObject;
use Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;

/**
 * What a merchant asks Polkurier to ship; see the interface.
 */
class Draft extends DataObject implements ShipmentDraftInterface
{
    public function getCarrierCode(): string
    {
        $value = $this->getData(self::FIELD_CARRIER_CODE);

        return (string)$value;
    }

    public function setCarrierCode(string $carrierCode): static
    {
        return $this->setData(self::FIELD_CARRIER_CODE, $carrierCode);
    }

    /**
     * What its parcels are (ParcelTypeMapper); the type set is for a shipment with no parcels yet, or
     * parcels of more than one kind, which can't go.
     */
    public function getShipmentType(): string
    {
        $types = ParcelTypeMapper::shipmentTypesOf(\array_map(
            static fn(PackInterface $pack): string => $pack->getPackType(),
            $this->getPacks(),
        ));

        return \count($types) === 1 ? $types[0]->value : (string)$this->getData(self::FIELD_SHIPMENT_TYPE);
    }

    public function setShipmentType(string $shipmentType): static
    {
        return $this->setData(self::FIELD_SHIPMENT_TYPE, $shipmentType);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData(self::FIELD_DESCRIPTION);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setDescription(?string $description): static
    {
        return $this->setData(self::FIELD_DESCRIPTION, $description);
    }

    public function getSenderAddressId(): ?int
    {
        $value = $this->getData(self::FIELD_SENDER_ADDRESS_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setSenderAddressId(?int $senderAddressId): static
    {
        return $this->setData(self::FIELD_SENDER_ADDRESS_ID, $senderAddressId);
    }

    public function getRecipientPerson(): string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_PERSON);

        return (string)$value;
    }

    public function setRecipientPerson(string $recipientPerson): static
    {
        return $this->setData(self::FIELD_RECIPIENT_PERSON, $recipientPerson);
    }

    public function getRecipientCompany(): ?string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_COMPANY);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientCompany(?string $recipientCompany): static
    {
        return $this->setData(self::FIELD_RECIPIENT_COMPANY, $recipientCompany);
    }

    public function getRecipientStreet(): string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_STREET);

        return (string)$value;
    }

    public function setRecipientStreet(string $recipientStreet): static
    {
        return $this->setData(self::FIELD_RECIPIENT_STREET, $recipientStreet);
    }

    public function getRecipientHouseNumber(): ?string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_HOUSE_NUMBER);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientHouseNumber(?string $recipientHouseNumber): static
    {
        return $this->setData(self::FIELD_RECIPIENT_HOUSE_NUMBER, $recipientHouseNumber);
    }

    public function getRecipientFlatNumber(): ?string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_FLAT_NUMBER);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientFlatNumber(?string $recipientFlatNumber): static
    {
        return $this->setData(self::FIELD_RECIPIENT_FLAT_NUMBER, $recipientFlatNumber);
    }

    public function getRecipientPostcode(): string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_POSTCODE);

        return (string)$value;
    }

    public function setRecipientPostcode(string $recipientPostcode): static
    {
        return $this->setData(self::FIELD_RECIPIENT_POSTCODE, $recipientPostcode);
    }

    public function getRecipientCity(): string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_CITY);

        return (string)$value;
    }

    public function setRecipientCity(string $recipientCity): static
    {
        return $this->setData(self::FIELD_RECIPIENT_CITY, $recipientCity);
    }

    public function getRecipientCountry(): string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_COUNTRY);

        return (string)$value;
    }

    public function setRecipientCountry(string $recipientCountry): static
    {
        return $this->setData(self::FIELD_RECIPIENT_COUNTRY, $recipientCountry);
    }

    public function getRecipientPhone(): string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_PHONE);

        return (string)$value;
    }

    public function setRecipientPhone(string $recipientPhone): static
    {
        return $this->setData(self::FIELD_RECIPIENT_PHONE, $recipientPhone);
    }

    public function getRecipientEmail(): string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_EMAIL);

        return (string)$value;
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        return $this->setData(self::FIELD_RECIPIENT_EMAIL, $recipientEmail);
    }

    public function getRecipientPointId(): ?string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_POINT_ID);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientPointId(?string $recipientPointId): static
    {
        return $this->setData(self::FIELD_RECIPIENT_POINT_ID, $recipientPointId);
    }

    public function getPacks(): array
    {
        $value = $this->getData(self::FIELD_PACKS);

        return \is_array($value) ? $value : [];
    }

    public function setPacks(array $packs): static
    {
        return $this->setData(self::FIELD_PACKS, $packs);
    }

    public function getItems(): array
    {
        $value = $this->getData(self::FIELD_ITEMS);

        return \is_array($value) ? $value : [];
    }

    public function setItems(array $items): static
    {
        return $this->setData(self::FIELD_ITEMS, $items);
    }

    public function isPacksEstimated(): bool
    {
        return (bool)$this->getData(self::FIELD_PACKS_ESTIMATED);
    }

    public function setPacksEstimated(bool $estimated): static
    {
        return $this->setData(self::FIELD_PACKS_ESTIMATED, $estimated);
    }

    public function isShippingIncluded(): bool
    {
        return (bool)$this->getData(self::FIELD_SHIPPING_INCLUDED);
    }

    public function setShippingIncluded(bool $included): static
    {
        return $this->setData(self::FIELD_SHIPPING_INCLUDED, $included);
    }

    public function getCodAmount(): ?float
    {
        $value = $this->getData(self::FIELD_COD_AMOUNT);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setCodAmount(?float $codAmount): static
    {
        return $this->setData(self::FIELD_COD_AMOUNT, $codAmount);
    }

    public function getCodType(): ?string
    {
        $value = $this->getData(self::FIELD_COD_TYPE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCodType(?string $codType): static
    {
        return $this->setData(self::FIELD_COD_TYPE, $codType);
    }

    public function getCodReturnType(): ?string
    {
        $value = $this->getData(self::FIELD_COD_RETURN_TYPE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCodReturnType(?string $codReturnType): static
    {
        return $this->setData(self::FIELD_COD_RETURN_TYPE, $codReturnType);
    }

    public function getCodBankAccountId(): ?int
    {
        $value = $this->getData(self::FIELD_COD_BANK_ACCOUNT_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setCodBankAccountId(?int $codBankAccountId): static
    {
        return $this->setData(self::FIELD_COD_BANK_ACCOUNT_ID, $codBankAccountId);
    }

    public function getSenderPointId(): ?string
    {
        $value = \trim((string)$this->getData(self::FIELD_SENDER_POINT_ID));

        return $value === '' ? null : $value;
    }

    public function setSenderPointId(?string $pointId): static
    {
        return $this->setData(self::FIELD_SENDER_POINT_ID, $pointId);
    }

    public function isNewSender(): bool
    {
        return (bool)$this->getData(self::FIELD_NEW_SENDER);
    }

    public function setNewSender(bool $newSender): static
    {
        return $this->setData(self::FIELD_NEW_SENDER, $newSender);
    }

    public function getNewSenderAddress(): array
    {
        $address = [];
        foreach (self::NEW_SENDER_FIELDS as $field => $key) {
            $address[$field] = \trim((string)$this->getData($key));
        }

        return $address;
    }

    public function setNewSenderAddress(array $address): static
    {
        foreach (self::NEW_SENDER_FIELDS as $field => $key) {
            $this->setData($key, \trim((string)($address[$field] ?? '')));
        }

        return $this;
    }

    public function isKeepingNewSender(): bool
    {
        return (bool)$this->getData(self::FIELD_KEEP_NEW_SENDER);
    }

    public function setKeepingNewSender(bool $keep): static
    {
        return $this->setData(self::FIELD_KEEP_NEW_SENDER, $keep);
    }

    public function getNewSenderName(): string
    {
        return \trim((string)$this->getData(self::FIELD_NEW_SENDER_NAME));
    }

    public function setNewSenderName(string $name): static
    {
        return $this->setData(self::FIELD_NEW_SENDER_NAME, $name);
    }

    public function isNewBankAccount(): bool
    {
        return (bool)$this->getData(self::FIELD_NEW_BANK_ACCOUNT);
    }

    public function setNewBankAccount(bool $newBankAccount): static
    {
        return $this->setData(self::FIELD_NEW_BANK_ACCOUNT, $newBankAccount);
    }

    public function getNewBankAccountNumber(): string
    {
        return \trim((string)$this->getData(self::FIELD_NEW_BANK_ACCOUNT_NUMBER));
    }

    public function setNewBankAccountNumber(string $number): static
    {
        return $this->setData(self::FIELD_NEW_BANK_ACCOUNT_NUMBER, $number);
    }

    public function isKeepingNewBankAccount(): bool
    {
        return (bool)$this->getData(self::FIELD_KEEP_NEW_BANK_ACCOUNT);
    }

    public function setKeepingNewBankAccount(bool $keep): static
    {
        return $this->setData(self::FIELD_KEEP_NEW_BANK_ACCOUNT, $keep);
    }

    public function getNewBankAccountName(): string
    {
        return \trim((string)$this->getData(self::FIELD_NEW_BANK_ACCOUNT_NAME));
    }

    public function setNewBankAccountName(string $name): static
    {
        return $this->setData(self::FIELD_NEW_BANK_ACCOUNT_NAME, $name);
    }

    public function getInsuranceAmount(): ?float
    {
        $value = $this->getData(self::FIELD_INSURANCE_AMOUNT);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setInsuranceAmount(?float $insuranceAmount): static
    {
        return $this->setData(self::FIELD_INSURANCE_AMOUNT, $insuranceAmount);
    }

    public function getCoverAddressId(): ?int
    {
        $value = $this->getData(self::FIELD_COVER_ADDRESS_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setCoverAddressId(?int $coverAddressId): static
    {
        return $this->setData(self::FIELD_COVER_ADDRESS_ID, $coverAddressId);
    }

    public function getServices(): array
    {
        $value = $this->getData(self::FIELD_SERVICES);

        return \is_array($value) ? $value : [];
    }

    public function setServices(array $services): static
    {
        return $this->setData(self::FIELD_SERVICES, $services);
    }

    public function getPickupMode(): string
    {
        $value = $this->getData(self::FIELD_PICKUP_MODE);

        return (string)$value;
    }

    public function setPickupMode(string $pickupMode): static
    {
        return $this->setData(self::FIELD_PICKUP_MODE, $pickupMode);
    }

    public function getPickupDate(): ?string
    {
        $value = $this->getData(self::FIELD_PICKUP_DATE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPickupDate(?string $pickupDate): static
    {
        return $this->setData(self::FIELD_PICKUP_DATE, $pickupDate);
    }

    public function getPickupTimeFrom(): ?string
    {
        $value = $this->getData(self::FIELD_PICKUP_TIME_FROM);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPickupTimeFrom(?string $pickupTimeFrom): static
    {
        return $this->setData(self::FIELD_PICKUP_TIME_FROM, $pickupTimeFrom);
    }

    public function getPickupTimeTo(): ?string
    {
        $value = $this->getData(self::FIELD_PICKUP_TIME_TO);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPickupTimeTo(?string $pickupTimeTo): static
    {
        return $this->setData(self::FIELD_PICKUP_TIME_TO, $pickupTimeTo);
    }

    public function getAdditionalFields(): array
    {
        $value = $this->getData(self::FIELD_ADDITIONAL_FIELDS);

        return \is_array($value) ? $value : [];
    }

    public function setAdditionalFields(array $additionalFields): static
    {
        return $this->setData(self::FIELD_ADDITIONAL_FIELDS, $additionalFields);
    }

    public function getTargetShipmentId(): ?int
    {
        $value = $this->getData(self::FIELD_TARGET_SHIPMENT_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setTargetShipmentId(?int $targetShipmentId): static
    {
        return $this->setData(self::FIELD_TARGET_SHIPMENT_ID, $targetShipmentId);
    }
}
