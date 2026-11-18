<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * A Polkurier order placed for a Magento order.
 */
class Shipment extends AbstractModel implements ShipmentInterface
{
    public const string ID_FIELD = self::FIELD_ENTITY_ID;

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_shipment';

    /**
     * @var string
     */
    protected $_eventObject = 'shipment';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        private readonly Json $json,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    public function getOrderId(): int
    {
        return (int)$this->getData(self::FIELD_ORDER_ID);
    }

    public function setOrderId(int $orderId): static
    {
        return $this->setData(self::FIELD_ORDER_ID, $orderId);
    }

    public function getStoreId(): ?int
    {
        return $this->getNullableInt(self::FIELD_STORE_ID);
    }

    public function setStoreId(?int $storeId): static
    {
        return $this->setData(self::FIELD_STORE_ID, $storeId);
    }

    public function getShipmentId(): ?int
    {
        return $this->getNullableInt(self::FIELD_SHIPMENT_ID);
    }

    public function setShipmentId(?int $shipmentId): static
    {
        return $this->setData(self::FIELD_SHIPMENT_ID, $shipmentId);
    }

    public function getState(): string
    {
        return (string)$this->getData(self::FIELD_STATE);
    }

    public function setState(string $state): static
    {
        return $this->setData(self::FIELD_STATE, $state);
    }

    public function getPolkurierNumber(): ?string
    {
        return $this->getNullableString(self::FIELD_POLKURIER_NUMBER);
    }

    public function setPolkurierNumber(?string $polkurierNumber): static
    {
        return $this->setData(self::FIELD_POLKURIER_NUMBER, $polkurierNumber);
    }

    public function getCarrierCode(): string
    {
        return (string)$this->getData(self::FIELD_CARRIER_CODE);
    }

    public function setCarrierCode(string $carrierCode): static
    {
        return $this->setData(self::FIELD_CARRIER_CODE, $carrierCode);
    }

    public function getDescription(): string
    {
        return (string)$this->getData(self::FIELD_DESCRIPTION);
    }

    public function setDescription(string $description): static
    {
        return $this->setData(self::FIELD_DESCRIPTION, $description);
    }

    public function getRecipientPostcode(): ?string
    {
        return $this->getNullableString(self::FIELD_RECIPIENT_POSTCODE);
    }

    public function setRecipientPostcode(?string $recipientPostcode): static
    {
        return $this->setData(self::FIELD_RECIPIENT_POSTCODE, $recipientPostcode);
    }

    public function getRecipientPhone(): ?string
    {
        return $this->getNullableString(self::FIELD_RECIPIENT_PHONE);
    }

    public function setRecipientPhone(?string $recipientPhone): static
    {
        return $this->setData(self::FIELD_RECIPIENT_PHONE, $recipientPhone);
    }

    public function getPackCount(): int
    {
        return (int)$this->getData(self::FIELD_PACK_COUNT);
    }

    public function setPackCount(int $packCount): static
    {
        return $this->setData(self::FIELD_PACK_COUNT, $packCount);
    }

    public function getWaybills(): array
    {
        $value = $this->getData(self::FIELD_WAYBILLS);
        if (\is_array($value)) {
            return \array_values(\array_map('strval', $value));
        }

        return \array_values(\array_filter(
            \explode(',', (string)$value),
            static fn(string $item): bool => $item !== '',
        ));
    }

    public function setWaybills(array $waybills): static
    {
        return $this->setData(self::FIELD_WAYBILLS, \implode(',', $waybills));
    }

    public function getStatusCode(): ?string
    {
        return $this->getNullableString(self::FIELD_STATUS_CODE);
    }

    public function setStatusCode(?string $statusCode): static
    {
        return $this->setData(self::FIELD_STATUS_CODE, $statusCode);
    }

    public function getStatusLabel(): ?string
    {
        return $this->getNullableString(self::FIELD_STATUS_LABEL);
    }

    public function setStatusLabel(?string $statusLabel): static
    {
        return $this->setData(self::FIELD_STATUS_LABEL, $statusLabel);
    }

    public function getStatusDate(): ?string
    {
        return $this->getNullableString(self::FIELD_STATUS_DATE);
    }

    public function setStatusDate(?string $statusDate): static
    {
        return $this->setData(self::FIELD_STATUS_DATE, $statusDate);
    }

    public function getDeliveredAt(): ?string
    {
        return $this->getNullableString(self::FIELD_DELIVERED_AT);
    }

    public function setDeliveredAt(?string $deliveredAt): static
    {
        return $this->setData(self::FIELD_DELIVERED_AT, $deliveredAt);
    }

    public function getTrackingUrl(): ?string
    {
        return $this->getNullableString(self::FIELD_TRACKING_URL);
    }

    public function setTrackingUrl(?string $trackingUrl): static
    {
        return $this->setData(self::FIELD_TRACKING_URL, $trackingUrl);
    }

    public function getPriceNet(): ?float
    {
        return $this->getNullableFloat(self::FIELD_PRICE_NET);
    }

    public function setPriceNet(?float $priceNet): static
    {
        return $this->setData(self::FIELD_PRICE_NET, $priceNet);
    }

    public function getPriceGross(): ?float
    {
        return $this->getNullableFloat(self::FIELD_PRICE_GROSS);
    }

    public function setPriceGross(?float $priceGross): static
    {
        return $this->setData(self::FIELD_PRICE_GROSS, $priceGross);
    }

    public function getInsuranceAmount(): ?float
    {
        return $this->getNullableFloat(self::FIELD_INSURANCE_AMOUNT);
    }

    public function setInsuranceAmount(?float $insuranceAmount): static
    {
        return $this->setData(self::FIELD_INSURANCE_AMOUNT, $insuranceAmount);
    }

    public function getCodAmount(): ?float
    {
        return $this->getNullableFloat(self::FIELD_COD_AMOUNT);
    }

    public function setCodAmount(?float $codAmount): static
    {
        return $this->setData(self::FIELD_COD_AMOUNT, $codAmount);
    }

    public function getCodStatus(): ?string
    {
        return $this->getNullableString(self::FIELD_COD_STATUS);
    }

    public function setCodStatus(?string $codStatus): static
    {
        return $this->setData(self::FIELD_COD_STATUS, $codStatus);
    }

    public function isMultiPickup(): bool
    {
        return (bool)$this->getData(self::FIELD_MULTI_PICKUP);
    }

    public function setMultiPickup(bool $multiPickup): static
    {
        return $this->setData(self::FIELD_MULTI_PICKUP, (int)$multiPickup);
    }

    public function isPickupBooked(): bool
    {
        return (bool)$this->getData(self::FIELD_PICKUP_BOOKED);
    }

    public function setPickupBooked(bool $pickupBooked): static
    {
        return $this->setData(self::FIELD_PICKUP_BOOKED, (int)$pickupBooked);
    }

    public function isFinal(): bool
    {
        return (bool)$this->getData(self::FIELD_IS_FINAL);
    }

    public function setIsFinal(bool $final): static
    {
        return $this->setData(self::FIELD_IS_FINAL, (int)$final);
    }

    public function getStatusCheckedAt(): ?string
    {
        return $this->getNullableString(self::FIELD_STATUS_CHECKED_AT);
    }

    public function setStatusCheckedAt(?string $statusCheckedAt): static
    {
        return $this->setData(self::FIELD_STATUS_CHECKED_AT, $statusCheckedAt);
    }

    public function getRequest(): ?string
    {
        return $this->getNullableString(self::FIELD_REQUEST);
    }

    public function setRequest(?string $request): static
    {
        return $this->setData(self::FIELD_REQUEST, $request);
    }

    public function getDraft(): array
    {
        $draft = [];
        foreach ($this->decode($this->getData(self::FIELD_DRAFT)) as $key => $value) {
            $draft[(string)$key] = $value;
        }

        return $draft;
    }

    public function setDraft(?array $draft): static
    {
        // Nothing filled in means no draft.
        return $this->setData(self::FIELD_DRAFT, $draft ? $this->encode($draft) : null);
    }

    public function getDetails(): array
    {
        return $this->decode($this->getData(self::FIELD_DETAILS));
    }

    public function setDetails(?array $details): static
    {
        // Empty is read, with nothing to show; null is not read yet.
        return $this->setData(self::FIELD_DETAILS, $details === null ? null : $this->encode($details));
    }

    public function getDetailsSyncedAt(): ?string
    {
        return $this->getNullableString(self::FIELD_DETAILS_SYNCED_AT);
    }

    public function setDetailsSyncedAt(?string $syncedAt): static
    {
        return $this->setData(self::FIELD_DETAILS_SYNCED_AT, $syncedAt);
    }

    public function getTracking(): array
    {
        return \array_values($this->decode($this->getData(self::FIELD_TRACKING)));
    }

    public function setTracking(?array $tracking): static
    {
        // Empty is read, with no events yet; null is not read yet.
        return $this->setData(
            self::FIELD_TRACKING,
            $tracking === null ? null : $this->encode(\array_values($tracking)),
        );
    }

    public function getTrackingSyncedAt(): ?string
    {
        return $this->getNullableString(self::FIELD_TRACKING_SYNCED_AT);
    }

    public function setTrackingSyncedAt(?string $syncedAt): static
    {
        return $this->setData(self::FIELD_TRACKING_SYNCED_AT, $syncedAt);
    }

    public function getShipItems(): array
    {
        $qtys = [];
        foreach ($this->decode($this->getData(self::FIELD_SHIP_ITEMS)) as $id => $qty) {
            $qtys[(int)$id] = (float)$qty;
        }

        return $qtys;
    }

    public function setShipItems(?array $qtys): static
    {
        return $this->setData(self::FIELD_SHIP_ITEMS, $qtys ? $this->encode((object)$qtys) : null);
    }

    public function getTargetShipmentId(): ?int
    {
        return $this->getNullableInt(self::FIELD_TARGET_SHIPMENT_ID);
    }

    public function setTargetShipmentId(?int $shipmentId): static
    {
        return $this->setData(self::FIELD_TARGET_SHIPMENT_ID, $shipmentId);
    }

    public function getExternalOrderId(): ?string
    {
        return $this->getNullableString(self::FIELD_EXTERNAL_ORDER_ID);
    }

    public function setExternalOrderId(?string $externalOrderId): static
    {
        return $this->setData(self::FIELD_EXTERNAL_ORDER_ID, $externalOrderId);
    }

    public function getReservedShipmentNumber(): ?string
    {
        return $this->getNullableString(self::FIELD_RESERVED_SHIPMENT_NUMBER);
    }

    public function setReservedShipmentNumber(?string $number): static
    {
        return $this->setData(self::FIELD_RESERVED_SHIPMENT_NUMBER, $number);
    }

    public function getShipPackages(): array
    {
        $packages = [];
        foreach ($this->decode($this->getData(self::FIELD_SHIP_PACKAGES)) as $number => $package) {
            $packages[(int)$number] = $package;
        }

        return $packages;
    }

    public function setShipPackages(?array $packages): static
    {
        return $this->setData(self::FIELD_SHIP_PACKAGES, $packages ? $this->encode((object)$packages) : null);
    }

    public function getLastError(): ?string
    {
        return $this->getNullableString(self::FIELD_LAST_ERROR);
    }

    public function setLastError(?string $lastError): static
    {
        return $this->setData(self::FIELD_LAST_ERROR, $lastError);
    }

    public function getAttentionNotifiedAt(): ?string
    {
        return $this->getNullableString(self::FIELD_ATTENTION_NOTIFIED_AT);
    }

    public function setAttentionNotifiedAt(?string $notifiedAt): static
    {
        return $this->setData(self::FIELD_ATTENTION_NOTIFIED_AT, $notifiedAt);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getNullableString(self::FIELD_CREATED_AT);
    }

    public function setCreatedAt(?string $createdAt): static
    {
        return $this->setData(self::FIELD_CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getNullableString(self::FIELD_UPDATED_AT);
    }

    public function setUpdatedAt(?string $updatedAt): static
    {
        return $this->setData(self::FIELD_UPDATED_AT, $updatedAt);
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Shipment::class);
    }

    /**
     * @param array<mixed>|object $value an object for keys that are numbers
     *
     * @throws \InvalidArgumentException when the value can't be encoded; it's never stored as something else
     */
    private function encode(array|object $value): string
    {
        return (string)$this->json->serialize($value);
    }

    /**
     * @return array<mixed>
     */
    private function decode(mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }
        try {
            $decoded = \is_string($value) && $value !== '' ? $this->json->unserialize($value) : null;
        } catch (\InvalidArgumentException) {
            $decoded = null;
        }

        return \is_array($decoded) ? $decoded : [];
    }

    private function getNullableString(string $field): ?string
    {
        $value = $this->getData($field);

        return $value === null || $value === '' ? null : (string)$value;
    }

    private function getNullableInt(string $field): ?int
    {
        $value = $this->getData($field);

        return $value === null || $value === '' ? null : (int)$value;
    }

    private function getNullableFloat(string $field): ?float
    {
        $value = $this->getData($field);

        return $value === null || $value === '' ? null : (float)$value;
    }
}
