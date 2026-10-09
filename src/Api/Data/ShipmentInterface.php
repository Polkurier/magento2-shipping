<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * A Polkurier order placed for a Magento order.
 *
 * @api
 */
interface ShipmentInterface
{
    /**
     * Being sent, or its sending was interrupted before an answer arrived.
     */
    public const string STATE_PENDING = 'pending';

    /**
     * Placed in Polkurier.
     */
    public const string STATE_CREATED = 'created';

    /**
     * Sent, but no answer arrived: it may or may not exist in Polkurier. Reconcile before retrying.
     */
    public const string STATE_UNKNOWN = 'unknown';

    /**
     * Refused, or confirmed absent after reconciliation; safe to send again.
     */
    public const string STATE_FAILED = 'failed';

    public const string STATE_CANCELLED = 'cancelled';

    /**
     * Polkurier orders that are, or may be, live: an unconfirmed one may exist. They hold on to
     * their Magento shipment and collect their cash on delivery; a cancelled or refused one doesn't.
     */
    public const array LIVE_STATES = [self::STATE_PENDING, self::STATE_CREATED, self::STATE_UNKNOWN];

    public const string FIELD_ENTITY_ID = 'entity_id';
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_STORE_ID = 'store_id';
    public const string FIELD_SHIPMENT_ID = 'shipment_id';
    public const string FIELD_STATE = 'state';
    public const string FIELD_POLKURIER_NUMBER = 'polkurier_number';
    public const string FIELD_CARRIER_CODE = 'carrier_code';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_RECIPIENT_POSTCODE = 'recipient_postcode';
    public const string FIELD_RECIPIENT_PHONE = 'recipient_phone';
    public const string FIELD_PACK_COUNT = 'pack_count';
    public const string FIELD_WAYBILLS = 'waybills';
    public const string FIELD_STATUS_CODE = 'status_code';
    public const string FIELD_STATUS_LABEL = 'status_label';
    public const string FIELD_STATUS_DATE = 'status_date';
    public const string FIELD_DELIVERED_AT = 'delivered_at';
    public const string FIELD_TRACKING_URL = 'tracking_url';
    public const string FIELD_PRICE_NET = 'price_net';
    public const string FIELD_PRICE_GROSS = 'price_gross';
    public const string FIELD_INSURANCE_AMOUNT = 'insurance_amount';
    public const string FIELD_COD_AMOUNT = 'cod_amount';
    public const string FIELD_COD_STATUS = 'cod_status';
    public const string FIELD_MULTI_PICKUP = 'multi_pickup';
    public const string FIELD_PICKUP_BOOKED = 'pickup_booked';
    public const string FIELD_IS_FINAL = 'is_final';
    public const string FIELD_STATUS_CHECKED_AT = 'status_checked_at';
    public const string FIELD_REQUEST = 'request';
    public const string FIELD_DRAFT = 'draft';
    public const string FIELD_DETAILS = 'details';
    public const string FIELD_DETAILS_SYNCED_AT = 'details_synced_at';
    public const string FIELD_TRACKING = 'tracking';
    public const string FIELD_TRACKING_SYNCED_AT = 'tracking_synced_at';
    public const string FIELD_SHIP_ITEMS = 'ship_items';
    public const string FIELD_TARGET_SHIPMENT_ID = 'target_shipment_id';
    public const string FIELD_EXTERNAL_ORDER_ID = 'external_order_id';
    public const string FIELD_RESERVED_SHIPMENT_NUMBER = 'reserved_shipment_number';
    public const string FIELD_SHIP_PACKAGES = 'ship_packages';
    public const string FIELD_LAST_ERROR = 'last_error';
    public const string FIELD_ATTENTION_NOTIFIED_AT = 'attention_notified_at';
    public const string FIELD_CREATED_AT = 'created_at';
    public const string FIELD_UPDATED_AT = 'updated_at';

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
     *
     * @return $this
     */
    public function setOrderId(int $orderId): static;

    /**
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * @param int|null $storeId
     *
     * @return $this
     */
    public function setStoreId(?int $storeId): static;

    /**
     * @return int|null
     */
    public function getShipmentId(): ?int;

    /**
     * @param int|null $shipmentId
     *
     * @return $this
     */
    public function setShipmentId(?int $shipmentId): static;

    /**
     * @return string
     */
    public function getState(): string;

    /**
     * @param string $state
     *
     * @return $this
     */
    public function setState(string $state): static;

    /**
     * @return string|null
     */
    public function getPolkurierNumber(): ?string;

    /**
     * @param string|null $polkurierNumber
     *
     * @return $this
     */
    public function setPolkurierNumber(?string $polkurierNumber): static;

    /**
     * @return string
     */
    public function getCarrierCode(): string;

    /**
     * @param string $carrierCode
     *
     * @return $this
     */
    public function setCarrierCode(string $carrierCode): static;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @param string $description
     *
     * @return $this
     */
    public function setDescription(string $description): static;

    /**
     * @return string|null
     */
    public function getRecipientPostcode(): ?string;

    /**
     * @param string|null $recipientPostcode
     *
     * @return $this
     */
    public function setRecipientPostcode(?string $recipientPostcode): static;

    /**
     * @return string|null
     */
    public function getRecipientPhone(): ?string;

    /**
     * @param string|null $recipientPhone
     *
     * @return $this
     */
    public function setRecipientPhone(?string $recipientPhone): static;

    /**
     * @return int
     */
    public function getPackCount(): int;

    /**
     * @param int $packCount
     *
     * @return $this
     */
    public function setPackCount(int $packCount): static;

    /**
     * @return string[]
     */
    public function getWaybills(): array;

    /**
     * @param string[] $waybills
     *
     * @return $this
     */
    public function setWaybills(array $waybills): static;

    /**
     * @return string|null
     */
    public function getStatusCode(): ?string;

    /**
     * @param string|null $statusCode
     *
     * @return $this
     */
    public function setStatusCode(?string $statusCode): static;

    /**
     * @return string|null
     */
    public function getStatusLabel(): ?string;

    /**
     * @param string|null $statusLabel
     *
     * @return $this
     */
    public function setStatusLabel(?string $statusLabel): static;

    /**
     * @return string|null
     */
    public function getStatusDate(): ?string;

    /**
     * @param string|null $statusDate
     *
     * @return $this
     */
    public function setStatusDate(?string $statusDate): static;

    /**
     * @return string|null
     */
    public function getDeliveredAt(): ?string;

    /**
     * @param string|null $deliveredAt
     *
     * @return $this
     */
    public function setDeliveredAt(?string $deliveredAt): static;

    /**
     * @return string|null
     */
    public function getTrackingUrl(): ?string;

    /**
     * @param string|null $trackingUrl
     *
     * @return $this
     */
    public function setTrackingUrl(?string $trackingUrl): static;

    /**
     * @return float|null
     */
    public function getPriceNet(): ?float;

    /**
     * @param float|null $priceNet
     *
     * @return $this
     */
    public function setPriceNet(?float $priceNet): static;

    /**
     * @return float|null
     */
    public function getPriceGross(): ?float;

    /**
     * @param float|null $priceGross
     *
     * @return $this
     */
    public function setPriceGross(?float $priceGross): static;

    /**
     * @return float|null
     */
    public function getInsuranceAmount(): ?float;

    /**
     * @param float|null $insuranceAmount
     *
     * @return $this
     */
    public function setInsuranceAmount(?float $insuranceAmount): static;

    /**
     * @return float|null
     */
    public function getCodAmount(): ?float;

    /**
     * @param float|null $codAmount
     *
     * @return $this
     */
    public function setCodAmount(?float $codAmount): static;

    /**
     * @return string|null
     */
    public function getCodStatus(): ?string;

    /**
     * @param string|null $codStatus
     *
     * @return $this
     */
    public function setCodStatus(?string $codStatus): static;

    /**
     * @return bool
     */
    public function isMultiPickup(): bool;

    /**
     * @param bool $multiPickup
     *
     * @return $this
     */
    public function setMultiPickup(bool $multiPickup): static;

    /**
     * @return bool
     */
    public function isPickupBooked(): bool;

    /**
     * @param bool $pickupBooked
     *
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
     *
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
     *
     * @return $this
     */
    public function setStatusCheckedAt(?string $statusCheckedAt): static;

    /**
     * @return string|null
     */
    public function getRequest(): ?string;

    /**
     * @param string|null $request
     *
     * @return $this
     */
    public function setRequest(?string $request): static;

    /**
     * The shipment as it was filled in when it was placed, in the form
     * {@see \Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator} gives. Sending it again starts
     * from this. Empty when it wasn't kept.
     *
     * @return array<string, mixed>
     */
    public function getDraft(): array;

    /**
     * @param array<string, mixed>|null $draft
     *
     * @return $this
     */
    public function setDraft(?array $draft): static;

    /**
     * The local copy of the order as Polkurier last reported it, or an empty array before it has
     * been read. See {@see \Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier::fromOrder()} for its shape.
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array;

    /**
     * @param array<string, mixed>|null $details
     *
     * @return $this
     */
    public function setDetails(?array $details): static;

    /**
     * @return string|null
     */
    public function getDetailsSyncedAt(): ?string;

    /**
     * @param string|null $syncedAt
     *
     * @return $this
     */
    public function setDetailsSyncedAt(?string $syncedAt): static;

    /**
     * The local copy of the carrier's scan history, newest first, or an empty array before it
     * has been read.
     *
     * @return array<int, array<string, string>>
     */
    public function getTracking(): array;

    /**
     * @param array<int, array<string, string>>|null $tracking
     *
     * @return $this
     */
    public function setTracking(?array $tracking): static;

    /**
     * @return string|null
     */
    public function getTrackingSyncedAt(): ?string;

    /**
     * @param string|null $syncedAt
     *
     * @return $this
     */
    public function setTrackingSyncedAt(?string $syncedAt): static;

    /**
     * The quantities the Magento shipment is to carry, by order item id; empty for everything the
     * order has left to ship.
     *
     * @return float[]
     */
    public function getShipItems(): array;

    /**
     * @param float[]|null $qtys by order item id
     *
     * @return $this
     */
    public function setShipItems(?array $qtys): static;

    /**
     * The existing Magento shipment the Polkurier order is to go on once placed, if it doesn't
     * ship items of its own.
     *
     * @return int|null
     */
    public function getTargetShipmentId(): ?int;

    /**
     * @param int|null $shipmentId
     *
     * @return $this
     */
    public function setTargetShipmentId(?int $shipmentId): static;

    /**
     * The external_order_id that, with the installation's application id, identifies the order to
     * Polkurier: the Magento shipment's number or the order's, as configured when the order was
     * sent.
     *
     * @return string|null
     */
    public function getExternalOrderId(): ?string;

    /**
     * @param string|null $externalOrderId
     *
     * @return $this
     */
    public function setExternalOrderId(?string $externalOrderId): static;

    /**
     * The number reserved for the order's Magento shipment before that shipment is created; the
     * shipment gets it once created. Sent as the external_order_id.
     *
     * @return string|null
     */
    public function getReservedShipmentNumber(): ?string;

    /**
     * @param string|null $number
     *
     * @return $this
     */
    public function setReservedShipmentNumber(?string $number): static;

    /**
     * The packages of the Magento shipment: the parcels and what each holds, in the form
     * Magento keeps them.
     *
     * @return mixed[]
     */
    public function getShipPackages(): array;

    /**
     * @param mixed[]|null $packages
     *
     * @return $this
     */
    public function setShipPackages(?array $packages): static;

    /**
     * @return string|null
     */
    public function getLastError(): ?string;

    /**
     * @param string|null $lastError
     *
     * @return $this
     */
    public function setLastError(?string $lastError): static;

    /**
     * When the admin was told the shipment needs attention
     * ({@see \Polkurier\Shipping\Model\Shipment\AttentionNotifier}); a new attempt clears it.
     *
     * @return string|null
     */
    public function getAttentionNotifiedAt(): ?string;

    /**
     * @param string|null $notifiedAt
     *
     * @return $this
     */
    public function setAttentionNotifiedAt(?string $notifiedAt): static;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string|null $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): static;

    /**
     * When the row was last changed in Magento.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * @param string|null $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(?string $updatedAt): static;
}
