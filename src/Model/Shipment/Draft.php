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
        return (string)$this->getData(self::FIELD_CARRIER_CODE);
    }

    public function setCarrierCode(string $carrierCode): static
    {
        return $this->setData(self::FIELD_CARRIER_CODE, $carrierCode);
    }

    /**
     * The type its parcels give ({@see ParcelTypeMapper}). The type set applies to a shipment with
     * no parcels yet, or with parcels of more than one kind, which can't be sent.
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
        return $this->nullableString(self::FIELD_DESCRIPTION);
    }

    public function setDescription(?string $description): static
    {
        return $this->setData(self::FIELD_DESCRIPTION, $description);
    }

    public function getSenderAddressId(): ?int
    {
        return $this->nullableInt(self::FIELD_SENDER_ADDRESS_ID);
    }

    public function setSenderAddressId(?int $senderAddressId): static
    {
        return $this->setData(self::FIELD_SENDER_ADDRESS_ID, $senderAddressId);
    }

    public function getRecipientPerson(): string
    {
        return (string)$this->getData(self::FIELD_RECIPIENT_PERSON);
    }

    public function setRecipientPerson(string $recipientPerson): static
    {
        return $this->setData(self::FIELD_RECIPIENT_PERSON, $recipientPerson);
    }

    public function getRecipientCompany(): ?string
    {
        return $this->nullableString(self::FIELD_RECIPIENT_COMPANY);
    }

    public function setRecipientCompany(?string $recipientCompany): static
    {
        return $this->setData(self::FIELD_RECIPIENT_COMPANY, $recipientCompany);
    }

    public function getRecipientStreet(): string
    {
        return (string)$this->getData(self::FIELD_RECIPIENT_STREET);
    }

    public function setRecipientStreet(string $recipientStreet): static
    {
        return $this->setData(self::FIELD_RECIPIENT_STREET, $recipientStreet);
    }

    public function getRecipientHouseNumber(): ?string
    {
        return $this->nullableString(self::FIELD_RECIPIENT_HOUSE_NUMBER);
    }

    public function setRecipientHouseNumber(?string $recipientHouseNumber): static
    {
        return $this->setData(self::FIELD_RECIPIENT_HOUSE_NUMBER, $recipientHouseNumber);
    }

    public function getRecipientFlatNumber(): ?string
    {
        return $this->nullableString(self::FIELD_RECIPIENT_FLAT_NUMBER);
    }

    public function setRecipientFlatNumber(?string $recipientFlatNumber): static
    {
        return $this->setData(self::FIELD_RECIPIENT_FLAT_NUMBER, $recipientFlatNumber);
    }

    public function getRecipientPostcode(): string
    {
        return (string)$this->getData(self::FIELD_RECIPIENT_POSTCODE);
    }

    public function setRecipientPostcode(string $recipientPostcode): static
    {
        return $this->setData(self::FIELD_RECIPIENT_POSTCODE, $recipientPostcode);
    }

    public function getRecipientCity(): string
    {
        return (string)$this->getData(self::FIELD_RECIPIENT_CITY);
    }

    public function setRecipientCity(string $recipientCity): static
    {
        return $this->setData(self::FIELD_RECIPIENT_CITY, $recipientCity);
    }

    public function getRecipientCountry(): string
    {
        return (string)$this->getData(self::FIELD_RECIPIENT_COUNTRY);
    }

    public function setRecipientCountry(string $recipientCountry): static
    {
        return $this->setData(self::FIELD_RECIPIENT_COUNTRY, $recipientCountry);
    }

    public function getRecipientPhone(): string
    {
        return (string)$this->getData(self::FIELD_RECIPIENT_PHONE);
    }

    public function setRecipientPhone(string $recipientPhone): static
    {
        return $this->setData(self::FIELD_RECIPIENT_PHONE, $recipientPhone);
    }

    public function getRecipientEmail(): string
    {
        return (string)$this->getData(self::FIELD_RECIPIENT_EMAIL);
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        return $this->setData(self::FIELD_RECIPIENT_EMAIL, $recipientEmail);
    }

    public function getRecipientPointId(): ?string
    {
        return $this->nullableString(self::FIELD_RECIPIENT_POINT_ID);
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
        return $this->nullableFloat(self::FIELD_COD_AMOUNT);
    }

    public function setCodAmount(?float $codAmount): static
    {
        return $this->setData(self::FIELD_COD_AMOUNT, $codAmount);
    }

    public function getCodType(): ?string
    {
        return $this->nullableString(self::FIELD_COD_TYPE);
    }

    public function setCodType(?string $codType): static
    {
        return $this->setData(self::FIELD_COD_TYPE, $codType);
    }

    public function getCodReturnType(): ?string
    {
        return $this->nullableString(self::FIELD_COD_RETURN_TYPE);
    }

    public function setCodReturnType(?string $codReturnType): static
    {
        return $this->setData(self::FIELD_COD_RETURN_TYPE, $codReturnType);
    }

    public function getCodBankAccountId(): ?int
    {
        return $this->nullableInt(self::FIELD_COD_BANK_ACCOUNT_ID);
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
        return $this->nullableFloat(self::FIELD_INSURANCE_AMOUNT);
    }

    public function setInsuranceAmount(?float $insuranceAmount): static
    {
        return $this->setData(self::FIELD_INSURANCE_AMOUNT, $insuranceAmount);
    }

    public function getCoverAddressId(): ?int
    {
        return $this->nullableInt(self::FIELD_COVER_ADDRESS_ID);
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
        return (string)$this->getData(self::FIELD_PICKUP_MODE);
    }

    public function setPickupMode(string $pickupMode): static
    {
        return $this->setData(self::FIELD_PICKUP_MODE, $pickupMode);
    }

    public function getPickupDate(): ?string
    {
        return $this->nullableString(self::FIELD_PICKUP_DATE);
    }

    public function setPickupDate(?string $pickupDate): static
    {
        return $this->setData(self::FIELD_PICKUP_DATE, $pickupDate);
    }

    public function getPickupTimeFrom(): ?string
    {
        return $this->nullableString(self::FIELD_PICKUP_TIME_FROM);
    }

    public function setPickupTimeFrom(?string $pickupTimeFrom): static
    {
        return $this->setData(self::FIELD_PICKUP_TIME_FROM, $pickupTimeFrom);
    }

    public function getPickupTimeTo(): ?string
    {
        return $this->nullableString(self::FIELD_PICKUP_TIME_TO);
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
        return $this->nullableInt(self::FIELD_TARGET_SHIPMENT_ID);
    }

    public function setTargetShipmentId(?int $targetShipmentId): static
    {
        return $this->setData(self::FIELD_TARGET_SHIPMENT_ID, $targetShipmentId);
    }

    public function isNotifyCustomer(): bool
    {
        return (bool)$this->getData(self::FIELD_NOTIFY_CUSTOMER);
    }

    public function setNotifyCustomer(bool $notify): static
    {
        return $this->setData(self::FIELD_NOTIFY_CUSTOMER, $notify);
    }

    private function nullableString(string $field): ?string
    {
        $value = $this->getData($field);

        return $value === null || $value === '' ? null : (string)$value;
    }

    private function nullableInt(string $field): ?int
    {
        $value = $this->getData($field);

        return $value === null || $value === '' ? null : (int)$value;
    }

    private function nullableFloat(string $field): ?float
    {
        $value = $this->getData($field);

        return $value === null || $value === '' ? null : (float)$value;
    }
}
