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
    public const string ID_FIELD = 'entity_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_shipment';

    /**
     * @var string
     */
    protected $_eventObject = 'shipment';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Shipment::class);
    }

    public function getOrderId(): int
    {
        $value = $this->getData(self::ORDER_ID);

        return (int)$value;
    }

    public function setOrderId(int $orderId): static
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData(self::STORE_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setStoreId(?int $storeId): static
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getShipmentId(): ?int
    {
        $value = $this->getData(self::SHIPMENT_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    public function setShipmentId(?int $shipmentId): static
    {
        return $this->setData(self::SHIPMENT_ID, $shipmentId);
    }

    public function getState(): string
    {
        $value = $this->getData(self::STATE);

        return (string)$value;
    }

    public function setState(string $state): static
    {
        return $this->setData(self::STATE, $state);
    }

    public function getPolkurierNumber(): ?string
    {
        $value = $this->getData(self::POLKURIER_NUMBER);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPolkurierNumber(?string $polkurierNumber): static
    {
        return $this->setData(self::POLKURIER_NUMBER, $polkurierNumber);
    }

    public function getCarrierCode(): string
    {
        $value = $this->getData(self::CARRIER_CODE);

        return (string)$value;
    }

    public function setCarrierCode(string $carrierCode): static
    {
        return $this->setData(self::CARRIER_CODE, $carrierCode);
    }

    public function getDescription(): string
    {
        $value = $this->getData(self::DESCRIPTION);

        return (string)$value;
    }

    public function setDescription(string $description): static
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function getRecipientPostcode(): ?string
    {
        $value = $this->getData(self::RECIPIENT_POSTCODE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientPostcode(?string $recipientPostcode): static
    {
        return $this->setData(self::RECIPIENT_POSTCODE, $recipientPostcode);
    }

    public function getRecipientPhone(): ?string
    {
        $value = $this->getData(self::RECIPIENT_PHONE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRecipientPhone(?string $recipientPhone): static
    {
        return $this->setData(self::RECIPIENT_PHONE, $recipientPhone);
    }

    public function getPackCount(): int
    {
        $value = $this->getData(self::PACK_COUNT);

        return (int)$value;
    }

    public function setPackCount(int $packCount): static
    {
        return $this->setData(self::PACK_COUNT, $packCount);
    }

    public function getWaybills(): array
    {
        $value = $this->getData(self::WAYBILLS);
        if (\is_array($value)) {
            return \array_values(\array_map('strval', $value));
        }

        return \array_values(\array_filter(\explode(',', (string)$value), static fn(string $item): bool => $item !== ''));
    }

    public function setWaybills(array $waybills): static
    {
        return $this->setData(self::WAYBILLS, \implode(',', $waybills));
    }

    public function getStatusCode(): ?string
    {
        $value = $this->getData(self::STATUS_CODE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setStatusCode(?string $statusCode): static
    {
        return $this->setData(self::STATUS_CODE, $statusCode);
    }

    public function getStatusLabel(): ?string
    {
        $value = $this->getData(self::STATUS_LABEL);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setStatusLabel(?string $statusLabel): static
    {
        return $this->setData(self::STATUS_LABEL, $statusLabel);
    }

    public function getStatusDate(): ?string
    {
        $value = $this->getData(self::STATUS_DATE);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setStatusDate(?string $statusDate): static
    {
        return $this->setData(self::STATUS_DATE, $statusDate);
    }

    public function getDeliveredAt(): ?string
    {
        $value = $this->getData(self::DELIVERED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setDeliveredAt(?string $deliveredAt): static
    {
        return $this->setData(self::DELIVERED_AT, $deliveredAt);
    }

    public function getTrackingUrl(): ?string
    {
        $value = $this->getData(self::TRACKING_URL);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setTrackingUrl(?string $trackingUrl): static
    {
        return $this->setData(self::TRACKING_URL, $trackingUrl);
    }

    public function getPriceNet(): ?float
    {
        $value = $this->getData(self::PRICE_NET);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setPriceNet(?float $priceNet): static
    {
        return $this->setData(self::PRICE_NET, $priceNet);
    }

    public function getPriceGross(): ?float
    {
        $value = $this->getData(self::PRICE_GROSS);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setPriceGross(?float $priceGross): static
    {
        return $this->setData(self::PRICE_GROSS, $priceGross);
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

    public function getCodAmount(): ?float
    {
        $value = $this->getData(self::COD_AMOUNT);

        return $value === null || $value === '' ? null : (float)$value;
    }

    public function setCodAmount(?float $codAmount): static
    {
        return $this->setData(self::COD_AMOUNT, $codAmount);
    }

    public function getCodStatus(): ?string
    {
        $value = $this->getData(self::COD_STATUS);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCodStatus(?string $codStatus): static
    {
        return $this->setData(self::COD_STATUS, $codStatus);
    }

    public function isMultiPickup(): bool
    {
        $value = $this->getData(self::MULTI_PICKUP);

        return (bool)$value;
    }

    public function setMultiPickup(bool $multiPickup): static
    {
        return $this->setData(self::MULTI_PICKUP, (int)$multiPickup);
    }

    public function isPickupBooked(): bool
    {
        $value = $this->getData(self::PICKUP_BOOKED);

        return (bool)$value;
    }

    public function setPickupBooked(bool $pickupBooked): static
    {
        return $this->setData(self::PICKUP_BOOKED, (int)$pickupBooked);
    }

    public function isFinal(): bool
    {
        return (bool)$this->getData(self::IS_FINAL);
    }

    public function setIsFinal(bool $final): static
    {
        return $this->setData(self::IS_FINAL, (int)$final);
    }

    public function getStatusCheckedAt(): ?string
    {
        $value = $this->getData(self::STATUS_CHECKED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setStatusCheckedAt(?string $statusCheckedAt): static
    {
        return $this->setData(self::STATUS_CHECKED_AT, $statusCheckedAt);
    }

    public function getRequest(): ?string
    {
        $value = $this->getData(self::REQUEST);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setRequest(?string $request): static
    {
        return $this->setData(self::REQUEST, $request);
    }

    public function getLastError(): ?string
    {
        $value = $this->getData(self::LAST_ERROR);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setLastError(?string $lastError): static
    {
        return $this->setData(self::LAST_ERROR, $lastError);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setCreatedAt(?string $createdAt): static
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
