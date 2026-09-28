<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * A Polkurier order placed for a Magento order.
 */
interface ShipmentInterface
{
    /** Being sent, or its sending was interrupted before an answer arrived. */
    public const string STATE_PENDING = 'pending';
    /** Placed in Polkurier. */
    public const string STATE_CREATED = 'created';
    /** Sent, but no answer arrived: it may or may not exist in Polkurier. Reconcile before retrying. */
    public const string STATE_UNKNOWN = 'unknown';
    /** Refused, or confirmed absent after reconciliation; safe to send again. */
    public const string STATE_FAILED = 'failed';
    public const string STATE_CANCELLED = 'cancelled';

    public const string ENTITY_ID = 'entity_id';
    public const string ORDER_ID = 'order_id';
    public const string STORE_ID = 'store_id';
    public const string SHIPMENT_ID = 'shipment_id';
    public const string STATE = 'state';
    public const string POLKURIER_NUMBER = 'polkurier_number';
    public const string CARRIER_CODE = 'carrier_code';
    public const string DESCRIPTION = 'description';
    public const string RECIPIENT_POSTCODE = 'recipient_postcode';
    public const string RECIPIENT_PHONE = 'recipient_phone';
    public const string PACK_COUNT = 'pack_count';
    public const string WAYBILLS = 'waybills';
    public const string STATUS_CODE = 'status_code';
    public const string STATUS_LABEL = 'status_label';
    public const string STATUS_DATE = 'status_date';
    public const string DELIVERED_AT = 'delivered_at';
    public const string TRACKING_URL = 'tracking_url';
    public const string PRICE_NET = 'price_net';
    public const string PRICE_GROSS = 'price_gross';
    public const string INSURANCE_AMOUNT = 'insurance_amount';
    public const string COD_AMOUNT = 'cod_amount';
    public const string COD_STATUS = 'cod_status';
    public const string MULTI_PICKUP = 'multi_pickup';
    public const string PICKUP_BOOKED = 'pickup_booked';
    public const string IS_FINAL = 'is_final';
    public const string STATUS_CHECKED_AT = 'status_checked_at';
    public const string REQUEST = 'request';
    public const string LAST_ERROR = 'last_error';
    public const string CREATED_AT = 'created_at';
    public const string UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @param int $orderId
     * @return $this
     */
    public function setOrderId(int $orderId): static;

    /**
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * @param int|null $storeId
     * @return $this
     */
    public function setStoreId(?int $storeId): static;

    /**
     * @return int|null
     */
    public function getShipmentId(): ?int;

    /**
     * @param int|null $shipmentId
     * @return $this
     */
    public function setShipmentId(?int $shipmentId): static;

    /**
     * @return string
     */
    public function getState(): string;

    /**
     * @param string $state
     * @return $this
     */
    public function setState(string $state): static;

    /**
     * @return string|null
     */
    public function getPolkurierNumber(): ?string;

    /**
     * @param string|null $polkurierNumber
     * @return $this
     */
    public function setPolkurierNumber(?string $polkurierNumber): static;

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
     * @return string
     */
    public function getDescription(): string;

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description): static;

    /**
     * @return string|null
     */
    public function getRecipientPostcode(): ?string;

    /**
     * @param string|null $recipientPostcode
     * @return $this
     */
    public function setRecipientPostcode(?string $recipientPostcode): static;

    /**
     * @return string|null
     */
    public function getRecipientPhone(): ?string;

    /**
     * @param string|null $recipientPhone
     * @return $this
     */
    public function setRecipientPhone(?string $recipientPhone): static;

    /**
     * @return int
     */
    public function getPackCount(): int;

    /**
     * @param int $packCount
     * @return $this
     */
    public function setPackCount(int $packCount): static;

    /**
     * @return string[]
     */
    public function getWaybills(): array;

    /**
     * @param string[] $waybills
     * @return $this
     */
    public function setWaybills(array $waybills): static;

    /**
     * @return string|null
     */
    public function getStatusCode(): ?string;

    /**
     * @param string|null $statusCode
     * @return $this
     */
    public function setStatusCode(?string $statusCode): static;

    /**
     * @return string|null
     */
    public function getStatusLabel(): ?string;

    /**
     * @param string|null $statusLabel
     * @return $this
     */
    public function setStatusLabel(?string $statusLabel): static;

    /**
     * @return string|null
     */
    public function getStatusDate(): ?string;

    /**
     * @param string|null $statusDate
     * @return $this
     */
    public function setStatusDate(?string $statusDate): static;

    /**
     * @return string|null
     */
    public function getDeliveredAt(): ?string;

    /**
     * @param string|null $deliveredAt
     * @return $this
     */
    public function setDeliveredAt(?string $deliveredAt): static;

    /**
     * @return string|null
     */
    public function getTrackingUrl(): ?string;

    /**
     * @param string|null $trackingUrl
     * @return $this
     */
    public function setTrackingUrl(?string $trackingUrl): static;

    /**
     * @return float|null
     */
    public function getPriceNet(): ?float;

    /**
     * @param float|null $priceNet
     * @return $this
     */
    public function setPriceNet(?float $priceNet): static;

    /**
     * @return float|null
     */
    public function getPriceGross(): ?float;

    /**
     * @param float|null $priceGross
     * @return $this
     */
    public function setPriceGross(?float $priceGross): static;

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
    public function getCodStatus(): ?string;

    /**
     * @param string|null $codStatus
     * @return $this
     */
    public function setCodStatus(?string $codStatus): static;

    /**
     * @return bool
     */
    public function isMultiPickup(): bool;

    /**
     * @param bool $multiPickup
     * @return $this
     */
    public function setMultiPickup(bool $multiPickup): static;

    /**
     * @return bool
     */
    public function isPickupBooked(): bool;

    /**
     * @param bool $pickupBooked
     * @return $this
     */
    public function setPickupBooked(bool $pickupBooked): static;

    /**
     * Whether Polkurier's statuses for this shipment can no longer change, so it is not refreshed.
     *
     * @return bool
     */
    public function isFinal(): bool;

    /**
     * @param bool $final
     * @return $this
     */
    public function setIsFinal(bool $final): static;

    /**
     * When the statuses were last read from Polkurier, whether or not they changed.
     *
     * @return string|null
     */
    public function getStatusCheckedAt(): ?string;

    /**
     * @param string|null $statusCheckedAt
     * @return $this
     */
    public function setStatusCheckedAt(?string $statusCheckedAt): static;

    /**
     * @return string|null
     */
    public function getRequest(): ?string;

    /**
     * @param string|null $request
     * @return $this
     */
    public function setRequest(?string $request): static;

    /**
     * @return string|null
     */
    public function getLastError(): ?string;

    /**
     * @param string|null $lastError
     * @return $this
     */
    public function setLastError(?string $lastError): static;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string|null $createdAt
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): static;
}
