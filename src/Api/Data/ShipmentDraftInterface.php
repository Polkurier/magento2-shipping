<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * What a merchant asks Polkurier to ship for one Magento order.
 *
 * A cash-on-delivery amount of zero or null means no collection; an insurance amount of zero or
 * null means none. The sender address id refers to the local address book; null means the store
 * origin address. Pickup dates are Y-m-d, times H:i, in the store's local time.
 */
interface ShipmentDraftInterface
{
    /** A courier collects the parcels from the sender, on the date and in the window given. */
    public const string PICKUP_COURIER = 'courier';
    /** The sender takes the parcels to a point or branch; no collection is ordered. */
    public const string PICKUP_DROP_OFF = 'drop_off';
    /** No collection is ordered now; one collection is booked later for several shipments. */
    public const string PICKUP_MULTI = 'multi';

    public const string CARRIER_CODE = 'carrier_code';
    public const string SHIPMENT_TYPE = 'shipment_type';
    public const string DESCRIPTION = 'description';
    public const string SENDER_ADDRESS_ID = 'sender_address_id';
    /**
     * The point the parcels are handed over at, for carriers that take them at points; it starts
     * as the sender address's drop-off point.
     */
    public const string SENDER_POINT_ID = 'sender_point_id';
    /**
     * The choice of a sender address or bank account typed in for this shipment rather than one
     * of those stored, as the screens send it in place of an id.
     */
    public const string NEW = 'new';
    /**
     * Whether the sender is the one typed in for this shipment (NEW_SENDER_FIELDS).
     */
    public const string NEW_SENDER = 'new_sender';
    /**
     * The sender typed in, field by field, by the address field each one is.
     */
    public const array NEW_SENDER_FIELDS = [
        AddressInterface::PERSON => 'new_sender_person',
        AddressInterface::COMPANY => 'new_sender_company',
        AddressInterface::STREET => 'new_sender_street',
        AddressInterface::HOUSE_NUMBER => 'new_sender_house_number',
        AddressInterface::FLAT_NUMBER => 'new_sender_flat_number',
        AddressInterface::POSTCODE => 'new_sender_postcode',
        AddressInterface::CITY => 'new_sender_city',
        AddressInterface::COUNTRY => 'new_sender_country',
        AddressInterface::PHONE => 'new_sender_phone',
        AddressInterface::EMAIL => 'new_sender_email',
    ];
    /**
     * Whether the sender typed in is saved to the addresses, under NEW_SENDER_NAME, once the
     * shipment is placed.
     */
    public const string KEEP_NEW_SENDER = 'keep_new_sender';
    public const string NEW_SENDER_NAME = 'new_sender_name';
    public const string RECIPIENT_PERSON = 'recipient_person';
    public const string RECIPIENT_COMPANY = 'recipient_company';
    public const string RECIPIENT_STREET = 'recipient_street';
    public const string RECIPIENT_HOUSE_NUMBER = 'recipient_house_number';
    public const string RECIPIENT_FLAT_NUMBER = 'recipient_flat_number';
    public const string RECIPIENT_POSTCODE = 'recipient_postcode';
    public const string RECIPIENT_CITY = 'recipient_city';
    public const string RECIPIENT_COUNTRY = 'recipient_country';
    public const string RECIPIENT_PHONE = 'recipient_phone';
    public const string RECIPIENT_EMAIL = 'recipient_email';
    public const string RECIPIENT_POINT_ID = 'recipient_point_id';
    public const string PACKS = 'packs';
    public const string ITEMS = 'items';
    /**
     * Set on a draft whose parcels were packed automatically, which the screen asks to check.
     */
    public const string PACKS_ESTIMATED = 'packs_estimated';
    /**
     * Whether cash on delivery and insurance cover the shipping and handling charged.
     */
    public const string SHIPPING_INCLUDED = 'shipping_included';
    public const string COD_AMOUNT = 'cod_amount';
    public const string COD_TYPE = 'cod_type';
    public const string COD_RETURN_TYPE = 'cod_return_type';
    public const string COD_BANK_ACCOUNT_ID = 'cod_bank_account_id';
    /**
     * Whether cash on delivery is paid out to the account number typed in for this shipment.
     */
    public const string NEW_BANK_ACCOUNT = 'new_bank_account';
    public const string NEW_BANK_ACCOUNT_NUMBER = 'new_bank_account_number';
    /**
     * Whether that account is saved to the bank accounts, under NEW_BANK_ACCOUNT_NAME, once the
     * shipment is placed.
     */
    public const string KEEP_NEW_BANK_ACCOUNT = 'keep_new_bank_account';
    public const string NEW_BANK_ACCOUNT_NAME = 'new_bank_account_name';
    public const string INSURANCE_AMOUNT = 'insurance_amount';
    public const string COVER_ADDRESS_ID = 'cover_address_id';
    public const string SERVICES = 'services';
    public const string PICKUP_MODE = 'pickup_mode';
    public const string PICKUP_DATE = 'pickup_date';
    public const string PICKUP_TIME_FROM = 'pickup_time_from';
    public const string PICKUP_TIME_TO = 'pickup_time_to';
    public const string ADDITIONAL_FIELDS = 'additional_fields';
    public const string TARGET_SHIPMENT_ID = 'target_shipment_id';

    /**
     * @return string
     */
    public function getCarrierCode(): string;

    /**
     * @param string $carrierCode
     * @return $this
     */
    public function setCarrierCode(string $carrierCode): static;

    /**
     * The shipment type its parcels make: "box", "envelope" or "palette". Without parcels, or with
     * parcels of more than one kind, the one set.
     *
     * @return string
     */
    public function getShipmentType(): string;

    /**
     * @param string $shipmentType
     * @return $this
     */
    public function setShipmentType(string $shipmentType): static;

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
     * @return int|null
     */
    public function getSenderAddressId(): ?int;

    /**
     * @param int|null $senderAddressId
     * @return $this
     */
    public function setSenderAddressId(?int $senderAddressId): static;

    /**
     * @return string
     */
    public function getRecipientPerson(): string;

    /**
     * @param string $recipientPerson
     * @return $this
     */
    public function setRecipientPerson(string $recipientPerson): static;

    /**
     * @return string|null
     */
    public function getRecipientCompany(): ?string;

    /**
     * @param string|null $recipientCompany
     * @return $this
     */
    public function setRecipientCompany(?string $recipientCompany): static;

    /**
     * @return string
     */
    public function getRecipientStreet(): string;

    /**
     * @param string $recipientStreet
     * @return $this
     */
    public function setRecipientStreet(string $recipientStreet): static;

    /**
     * @return string|null
     */
    public function getRecipientHouseNumber(): ?string;

    /**
     * @param string|null $recipientHouseNumber
     * @return $this
     */
    public function setRecipientHouseNumber(?string $recipientHouseNumber): static;

    /**
     * @return string|null
     */
    public function getRecipientFlatNumber(): ?string;

    /**
     * @param string|null $recipientFlatNumber
     * @return $this
     */
    public function setRecipientFlatNumber(?string $recipientFlatNumber): static;

    /**
     * @return string
     */
    public function getRecipientPostcode(): string;

    /**
     * @param string $recipientPostcode
     * @return $this
     */
    public function setRecipientPostcode(string $recipientPostcode): static;

    /**
     * @return string
     */
    public function getRecipientCity(): string;

    /**
     * @param string $recipientCity
     * @return $this
     */
    public function setRecipientCity(string $recipientCity): static;

    /**
     * @return string
     */
    public function getRecipientCountry(): string;

    /**
     * @param string $recipientCountry
     * @return $this
     */
    public function setRecipientCountry(string $recipientCountry): static;

    /**
     * @return string
     */
    public function getRecipientPhone(): string;

    /**
     * @param string $recipientPhone
     * @return $this
     */
    public function setRecipientPhone(string $recipientPhone): static;

    /**
     * @return string
     */
    public function getRecipientEmail(): string;

    /**
     * @param string $recipientEmail
     * @return $this
     */
    public function setRecipientEmail(string $recipientEmail): static;

    /**
     * @return string|null
     */
    public function getRecipientPointId(): ?string;

    /**
     * @param string|null $recipientPointId
     * @return $this
     */
    public function setRecipientPointId(?string $recipientPointId): static;

    /**
     * @return \Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface[]
     */
    public function getPacks(): array;

    /**
     * @param \Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface[] $packs
     * @return $this
     */
    public function setPacks(array $packs): static;

    /**
     * The order's items the shipment carries in Magento. Empty for everything the order still
     * has to ship.
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface[]
     */
    public function getItems(): array;

    /**
     * @param \Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;

    /**
     * Whether the parcels were packed automatically, which the screen asks to check.
     *
     * @return bool
     */
    public function isPacksEstimated(): bool;

    /**
     * @param bool $estimated
     * @return $this
     */
    public function setPacksEstimated(bool $estimated): static;

    /**
     * Whether the cash on delivery and the insurance cover the shipping and handling charged,
     * besides the products (OrderAmounts::getShipmentWorth).
     *
     * @return bool
     */
    public function isShippingIncluded(): bool;

    /**
     * @param bool $included
     * @return $this
     */
    public function setShippingIncluded(bool $included): static;

    /**
     * @return float|null
     */
    public function getCodAmount(): ?float;

    /**
     * @param float|null $codAmount
     * @return $this
     */
    public function setCodAmount(?float $codAmount): static;

    /**
     * @return string|null
     */
    public function getCodType(): ?string;

    /**
     * @param string|null $codType
     * @return $this
     */
    public function setCodType(?string $codType): static;

    /**
     * @return string|null
     */
    public function getCodReturnType(): ?string;

    /**
     * @param string|null $codReturnType
     * @return $this
     */
    public function setCodReturnType(?string $codReturnType): static;

    /**
     * @return int|null
     */
    public function getCodBankAccountId(): ?int;

    /**
     * @param int|null $codBankAccountId
     * @return $this
     */
    public function setCodBankAccountId(?int $codBankAccountId): static;

    public function getSenderPointId(): ?string;

    public function setSenderPointId(?string $pointId): static;

    public function isNewSender(): bool;

    public function setNewSender(bool $newSender): static;

    /**
     * @return array<string, string> the sender typed in, by address field (NEW_SENDER_FIELDS)
     */
    public function getNewSenderAddress(): array;

    /**
     * @param array<string, string> $address by address field
     * @return $this
     */
    public function setNewSenderAddress(array $address): static;

    public function isKeepingNewSender(): bool;

    public function setKeepingNewSender(bool $keep): static;

    public function getNewSenderName(): string;

    public function setNewSenderName(string $name): static;

    public function isNewBankAccount(): bool;

    public function setNewBankAccount(bool $newBankAccount): static;

    public function getNewBankAccountNumber(): string;

    public function setNewBankAccountNumber(string $number): static;

    public function isKeepingNewBankAccount(): bool;

    public function setKeepingNewBankAccount(bool $keep): static;

    public function getNewBankAccountName(): string;

    public function setNewBankAccountName(string $name): static;

    /**
     * @return float|null
     */
    public function getInsuranceAmount(): ?float;

    /**
     * @param float|null $insuranceAmount
     * @return $this
     */
    public function setInsuranceAmount(?float $insuranceAmount): static;

    /**
     * @return int|null
     */
    public function getCoverAddressId(): ?int;

    /**
     * @param int|null $coverAddressId
     * @return $this
     */
    public function setCoverAddressId(?int $coverAddressId): static;

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
     * @return string
     */
    public function getPickupMode(): string;

    /**
     * @param string $pickupMode
     * @return $this
     */
    public function setPickupMode(string $pickupMode): static;

    /**
     * @return string|null
     */
    public function getPickupDate(): ?string;

    /**
     * @param string|null $pickupDate
     * @return $this
     */
    public function setPickupDate(?string $pickupDate): static;

    /**
     * @return string|null
     */
    public function getPickupTimeFrom(): ?string;

    /**
     * @param string|null $pickupTimeFrom
     * @return $this
     */
    public function setPickupTimeFrom(?string $pickupTimeFrom): static;

    /**
     * @return string|null
     */
    public function getPickupTimeTo(): ?string;

    /**
     * @param string|null $pickupTimeTo
     * @return $this
     */
    public function setPickupTimeTo(?string $pickupTimeTo): static;

    /**
     * @return string[]
     */
    public function getAdditionalFields(): array;

    /**
     * @param string[] $additionalFields
     * @return $this
     */
    public function setAdditionalFields(array $additionalFields): static;

    /**
     * The order's Magento shipment the Polkurier order goes on, one whose Polkurier order was
     * cancelled; null to ship items the order has left.
     *
     * @return int|null
     */
    public function getTargetShipmentId(): ?int;

    /**
     * @param int|null $targetShipmentId
     * @return $this
     */
    public function setTargetShipmentId(?int $targetShipmentId): static;
}
