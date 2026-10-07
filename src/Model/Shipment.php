<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;
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

    public function getOrderId(): int
    {
        $value = $this->getData(self::FIELD_ORDER_ID);

        return (int)$value;
    }

    public function setOrderId(int $orderId): static
    {
        return $this->setData(self::FIELD_ORDER_ID, $orderId);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData(self::FIELD_STORE_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setStoreId(?int $storeId): static
    {
        return $this->setData(self::FIELD_STORE_ID, $storeId);
    }

    public function getShipmentId(): ?int
    {
        $value = $this->getData(self::FIELD_SHIPMENT_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setShipmentId(?int $shipmentId): static
    {
        return $this->setData(self::FIELD_SHIPMENT_ID, $shipmentId);
    }

    public function getState(): string
    {
        $value = $this->getData(self::FIELD_STATE);

        return (string)$value;
    }

    public function setState(string $state): static
    {
        return $this->setData(self::FIELD_STATE, $state);
    }

    public function getPolkurierNumber(): ?string
    {
        $value = $this->getData(self::FIELD_POLKURIER_NUMBER);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPolkurierNumber(?string $polkurierNumber): static
    {
        return $this->setData(self::FIELD_POLKURIER_NUMBER, $polkurierNumber);
    }

    public function getCarrierCode(): string
    {
        $value = $this->getData(self::FIELD_CARRIER_CODE);

        return (string)$value;
    }

    public function setCarrierCode(string $carrierCode): static
    {
        return $this->setData(self::FIELD_CARRIER_CODE, $carrierCode);
    }

    public function getDescription(): string
    {
        $value = $this->getData(self::FIELD_DESCRIPTION);

        return (string)$value;
    }

    public function setDescription(string $description): static
    {
        return $this->setData(self::FIELD_DESCRIPTION, $description);
    }

    public function getRecipientPostcode(): ?string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_POSTCODE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientPostcode(?string $recipientPostcode): static
    {
        return $this->setData(self::FIELD_RECIPIENT_POSTCODE, $recipientPostcode);
    }

    public function getRecipientPhone(): ?string
    {
        $value = $this->getData(self::FIELD_RECIPIENT_PHONE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientPhone(?string $recipientPhone): static
    {
        return $this->setData(self::FIELD_RECIPIENT_PHONE, $recipientPhone);
    }

    public function getPackCount(): int
    {
        $value = $this->getData(self::FIELD_PACK_COUNT);

        return (int)$value;
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
        $value = $this->getData(self::FIELD_STATUS_CODE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setStatusCode(?string $statusCode): static
    {
        return $this->setData(self::FIELD_STATUS_CODE, $statusCode);
    }

    public function getStatusLabel(): ?string
    {
        $value = $this->getData(self::FIELD_STATUS_LABEL);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setStatusLabel(?string $statusLabel): static
    {
        return $this->setData(self::FIELD_STATUS_LABEL, $statusLabel);
    }

    public function getStatusDate(): ?string
    {
        $value = $this->getData(self::FIELD_STATUS_DATE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setStatusDate(?string $statusDate): static
    {
        return $this->setData(self::FIELD_STATUS_DATE, $statusDate);
    }

    public function getDeliveredAt(): ?string
    {
        $value = $this->getData(self::FIELD_DELIVERED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setDeliveredAt(?string $deliveredAt): static
    {
        return $this->setData(self::FIELD_DELIVERED_AT, $deliveredAt);
    }

    public function getTrackingUrl(): ?string
    {
        $value = $this->getData(self::FIELD_TRACKING_URL);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setTrackingUrl(?string $trackingUrl): static
    {
        return $this->setData(self::FIELD_TRACKING_URL, $trackingUrl);
    }

    public function getPriceNet(): ?float
    {
        $value = $this->getData(self::FIELD_PRICE_NET);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setPriceNet(?float $priceNet): static
    {
        return $this->setData(self::FIELD_PRICE_NET, $priceNet);
    }

    public function getPriceGross(): ?float
    {
        $value = $this->getData(self::FIELD_PRICE_GROSS);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setPriceGross(?float $priceGross): static
    {
        return $this->setData(self::FIELD_PRICE_GROSS, $priceGross);
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

    public function getCodAmount(): ?float
    {
        $value = $this->getData(self::FIELD_COD_AMOUNT);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setCodAmount(?float $codAmount): static
    {
        return $this->setData(self::FIELD_COD_AMOUNT, $codAmount);
    }

    public function getCodStatus(): ?string
    {
        $value = $this->getData(self::FIELD_COD_STATUS);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCodStatus(?string $codStatus): static
    {
        return $this->setData(self::FIELD_COD_STATUS, $codStatus);
    }

    public function isMultiPickup(): bool
    {
        $value = $this->getData(self::FIELD_MULTI_PICKUP);

        return (bool)$value;
    }

    public function setMultiPickup(bool $multiPickup): static
    {
        return $this->setData(self::FIELD_MULTI_PICKUP, (int)$multiPickup);
    }

    public function isPickupBooked(): bool
    {
        $value = $this->getData(self::FIELD_PICKUP_BOOKED);

        return (bool)$value;
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
        $value = $this->getData(self::FIELD_STATUS_CHECKED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setStatusCheckedAt(?string $statusCheckedAt): static
    {
        return $this->setData(self::FIELD_STATUS_CHECKED_AT, $statusCheckedAt);
    }

    public function getRequest(): ?string
    {
        $value = $this->getData(self::FIELD_REQUEST);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRequest(?string $request): static
    {
        return $this->setData(self::FIELD_REQUEST, $request);
    }

    public function getDraft(): array
    {
        $draft = [];
        foreach (self::decode($this->getData(self::FIELD_DRAFT)) as $key => $value) {
            $draft[(string)$key] = $value;
        }

        return $draft;
    }

    public function setDraft(?array $draft): static
    {
        // Nothing filled in is no draft.
        return $this->setData(self::FIELD_DRAFT, $draft ? self::encode($draft) : null);
    }

    public function getDetails(): array
    {
        return self::decode($this->getData(self::FIELD_DETAILS));
    }

    public function setDetails(?array $details): static
    {
        // Empty is read, with nothing to show; null is not read yet.
        return $this->setData(self::FIELD_DETAILS, $details === null ? null : self::encode($details));
    }

    public function getDetailsSyncedAt(): ?string
    {
        $value = $this->getData(self::FIELD_DETAILS_SYNCED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setDetailsSyncedAt(?string $syncedAt): static
    {
        return $this->setData(self::FIELD_DETAILS_SYNCED_AT, $syncedAt);
    }

    public function getTracking(): array
    {
        return \array_values(self::decode($this->getData(self::FIELD_TRACKING)));
    }

    public function setTracking(?array $tracking): static
    {
        // Empty is read, with no events yet; null is not read yet.
        return $this->setData(self::FIELD_TRACKING, $tracking === null ? null : self::encode(\array_values($tracking)));
    }

    public function getTrackingSyncedAt(): ?string
    {
        $value = $this->getData(self::FIELD_TRACKING_SYNCED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setTrackingSyncedAt(?string $syncedAt): static
    {
        return $this->setData(self::FIELD_TRACKING_SYNCED_AT, $syncedAt);
    }

    public function getShipItems(): array
    {
        $qtys = [];
        foreach (self::decode($this->getData(self::FIELD_SHIP_ITEMS)) as $id => $qty) {
            $qtys[(int)$id] = (float)$qty;
        }

        return $qtys;
    }

    public function setShipItems(?array $qtys): static
    {
        return $this->setData(self::FIELD_SHIP_ITEMS, $qtys ? self::encode((object)$qtys) : null);
    }

    public function getTargetShipmentId(): ?int
    {
        $value = $this->getData(self::FIELD_TARGET_SHIPMENT_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setTargetShipmentId(?int $shipmentId): static
    {
        return $this->setData(self::FIELD_TARGET_SHIPMENT_ID, $shipmentId);
    }

    public function getExternalOrderId(): ?string
    {
        $value = $this->getData(self::FIELD_EXTERNAL_ORDER_ID);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setExternalOrderId(?string $externalOrderId): static
    {
        return $this->setData(self::FIELD_EXTERNAL_ORDER_ID, $externalOrderId);
    }

    public function getReservedShipmentNumber(): ?string
    {
        $value = $this->getData(self::FIELD_RESERVED_SHIPMENT_NUMBER);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setReservedShipmentNumber(?string $number): static
    {
        return $this->setData(self::FIELD_RESERVED_SHIPMENT_NUMBER, $number);
    }

    public function getShipPackages(): array
    {
        $packages = [];
        foreach (self::decode($this->getData(self::FIELD_SHIP_PACKAGES)) as $number => $package) {
            $packages[(int)$number] = $package;
        }

        return $packages;
    }

    public function setShipPackages(?array $packages): static
    {
        return $this->setData(self::FIELD_SHIP_PACKAGES, $packages ? self::encode((object)$packages) : null);
    }

    public function getLastError(): ?string
    {
        $value = $this->getData(self::FIELD_LAST_ERROR);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setLastError(?string $lastError): static
    {
        return $this->setData(self::FIELD_LAST_ERROR, $lastError);
    }

    public function getAttentionNotifiedAt(): ?string
    {
        $value = $this->getData(self::FIELD_ATTENTION_NOTIFIED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setAttentionNotifiedAt(?string $notifiedAt): static
    {
        return $this->setData(self::FIELD_ATTENTION_NOTIFIED_AT, $notifiedAt);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::FIELD_CREATED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCreatedAt(?string $createdAt): static
    {
        return $this->setData(self::FIELD_CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::FIELD_UPDATED_AT);

        return $value === null || $value === '' ? null : (string)$value;
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
     * @throws \JsonException what can't be stored isn't stored as something else
     */
    private static function encode(array|object $value): string
    {
        return \json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<mixed>
     */
    private static function decode(mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }
        $decoded = \is_string($value) && $value !== '' ? \json_decode($value, true) : null;

        return \is_array($decoded) ? $decoded : [];
    }
}
