<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface as BaseOrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface as BaseShipmentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Api\Data\Order\AddressInterface;
use Polkurier\Shipping\Api\Data\Order\AddressInterfaceFactory;
use Polkurier\Shipping\Api\Data\Order\CodInterface;
use Polkurier\Shipping\Api\Data\Order\ItemInterface;
use Polkurier\Shipping\Api\Data\Order\PackInterface;
use Polkurier\Shipping\Api\Data\Order\WaybillInterface;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Model\ResourceModel\Order as OrderResource;
use Polkurier\Shipping\Model\ResourceModel\Order\Address\CollectionFactory as AddressCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod as CodResource;
use Polkurier\Shipping\Model\ResourceModel\Order\Item\Collection as ItemCollection;
use Polkurier\Shipping\Model\ResourceModel\Order\Item\CollectionFactory as ItemCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\Order\Pack\Collection as PackCollection;
use Polkurier\Shipping\Model\ResourceModel\Order\Pack\CollectionFactory as PackCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\Order\Waybill\Collection as WaybillCollection;
use Polkurier\Shipping\Model\ResourceModel\Order\Waybill\CollectionFactory as WaybillCollectionFactory;
use Polkurier\Webservice\Api\Data\Entity\Order\PickupInterface;
use Polkurier\Webservice\Api\Data\Entity\BaseAddressInterface;
use Polkurier\Webservice\Api\Data\Entity\Order\CodInterface as BaseCodInterface;
use Polkurier\Webservice\Enum\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\AddressTypeEnum;
use Polkurier\Webservice\Enum\ShipmentTypeEnum;

/**
 * @property OrderResource $_resource
 */
class Order extends AbstractModel implements OrderInterface
{
    use ExternalEntityTrait;

    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_PARENT_ORDER_ID = 'parent_order_id';
    public const string FIELD_PARENT_SHIPMENT_ID = 'parent_shipment_id';
    public const string FIELD_STATUS = 'status';
    public const string FIELD_STATUS_CODE = 'status_code';
    public const string FIELD_EXT_CREATED_AT = 'ext_created_at';
    public const string FIELD_STATUS_DATE = 'status_date';
    public const string FIELD_SHIPMENT_TYPE = 'shipment_type';
    public const string FIELD_CARRIER_CODE = 'carrier_code';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_PRICE_NET = 'price_net';
    public const string FIELD_PRICE_GROSS = 'price_gross';
    public const string FIELD_UNPAID_AMOUNT = 'unpaid_amount';
    public const string FIELD_PAID = 'paid';
    public const string FIELD_INVOICE = 'invoice';
    public const string FIELD_INSURANCE_AMOUNT = 'insurance_amount';
    public const string FIELD_COURIER_SERVICE = 'courier_service';
    public const string FIELD_PICKUP_FROM = 'pickup_from';
    public const string FIELD_PICKUP_TO = 'pickup_to';
    public const string FIELD_PICKUP_NO_COURIER_ORDER = 'pickup_no_courier_order';
    public const string FIELD_TRACKING_URI = 'tracking_uri';
    public const string FIELD_DELIVERED_AT = 'delivered_at';
    public const string FIELD_ADDITIONAL_FIELDS = 'additional_fields';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_order';

    /**
     * @var string
     */
    protected $_eventObject = 'polkurier_order';

    /**
     * @var bool
     */
    private bool $addressesLoaded = false;

    /**
     * @var BaseOrderInterface|null
     */
    private ?BaseOrderInterface $order = null;

    /**
     * @var BaseShipmentInterface|null
     */
    private ?BaseShipmentInterface $shipment = null;

    /**
     * @var AddressInterface|null
     */
    private ?AddressInterface $sender = null;

    /**
     * @var AddressInterface|null
     */
    private ?AddressInterface $recipient = null;

    /**
     * @var AddressInterface|null
     */
    private ?AddressInterface $coverAddress = null;

    /**
     * @var CodInterface|null
     */
    private ?CodInterface $cod = null;

    /**
     * @var PickupInterface|null
     */
    private ?PickupInterface $pickup = null;

    /**
     * @var iterable<ItemInterface>|null
     */
    private ?iterable $items = null;

    /**
     * @var iterable<PackInterface>|null
     */
    private ?iterable $packs = null;

    /**
     * @var iterable<WaybillInterface>|null
     */
    private ?iterable $waybills = null;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param AddressInterfaceFactory $addressFactory
     * @param ItemCollectionFactory $itemCollectionFactory
     * @param PackCollectionFactory $packCollectionFactory
     * @param WaybillCollectionFactory $waybillCollectionFactory
     * @param AddressCollectionFactory $addressCollectionFactory
     * @param CodResource $codResource
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     *
     * @throws LocalizedException
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly PackCollectionFactory $packCollectionFactory,
        private readonly WaybillCollectionFactory $waybillCollectionFactory,
        private readonly AddressCollectionFactory $addressCollectionFactory,
        private readonly CodResource $codResource,
        Context $context,
        Registry $registry,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(OrderResource::class);
    }

    /**
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return (int)$this->getData(static::FIELD_ORDER_ID) ?: null;
    }

    /**
     * @param int|null $orderId
     *
     * @return void
     */
    public function setOrderId(?int $orderId): void
    {
        $this->setData(static::FIELD_ORDER_ID, $orderId);
    }

    /**
     * @return int|null
     */
    public function getParentOrderId(): ?int
    {
        return (int)$this->getData(static::FIELD_PARENT_ORDER_ID) ?: null;
    }

    /**
     * @param int|null $parentOrderId
     *
     * @return void
     */
    public function setParentOrderId(?int $parentOrderId): void
    {
        $this->setData(static::FIELD_PARENT_ORDER_ID, $parentOrderId);
    }

    /**
     * @return BaseOrderInterface|null
     */
    public function getParentOrder(): ?BaseOrderInterface
    {
        if (!$this->order && $this->getParentOrderId()) {
            $this->order = $this->orderRepository->get($this->getParentOrderId());
        }

        return $this->order;
    }

    /**
     * @param BaseOrderInterface|null $order
     *
     * @return void
     */
    public function setParentOrder(?BaseOrderInterface $order): void
    {
        $this->order = $order;
        $this->setParentOrderId((int)$order->getId());
    }

    /**
     * @return int|null
     */
    public function getParentShipmentId(): ?int
    {
        return (int)$this->getData(static::FIELD_PARENT_SHIPMENT_ID) ?: null;
    }

    /**
     * @param int|null $parentShipmentId
     *
     * @return void
     */
    public function setParentShipmentId(?int $parentShipmentId): void
    {
        $this->setData(static::FIELD_PARENT_SHIPMENT_ID, $parentShipmentId);
    }

    /**
     * @return BaseShipmentInterface
     */
    public function getParentShipment(): BaseShipmentInterface
    {
        if (!$this->shipment && $this->getParentShipmentId()) {
            $this->shipment = $this->shipmentRepository->get($this->getParentShipmentId());
        }

        return $this->shipment;
    }

    /**
     * @param BaseShipmentInterface $shipment
     *
     * @return void
     */
    public function setParentShipment(BaseShipmentInterface $shipment): void
    {
        $this->shipment = $shipment;
        $this->setParentShipmentId((int)$shipment->getId());
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getExtCreatedAt(): ?\DateTimeInterface
    {
        return $this->getData(static::FIELD_EXT_CREATED_AT);
    }

    /**
     * @param \DateTimeInterface|null $date
     *
     * @return void
     */
    public function setExtCreatedAt(?\DateTimeInterface $date): void
    {
        $this->setData(static::FIELD_EXT_CREATED_AT, $date);
    }

    /**
     * @return string
     */
    public function getCarrierCode(): string
    {
        return $this->getData(static::FIELD_CARRIER_CODE);
    }

    /**
     * @param string $carrierCode
     *
     * @return void
     */
    public function setCarrierCode(string $carrierCode): void
    {
        $this->setData(static::FIELD_CARRIER_CODE, $carrierCode);
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getPickupFrom(): ?\DateTimeInterface
    {
        return $this->getData(static::FIELD_PICKUP_FROM);
    }

    /**
     * @param \DateTimeInterface|null $pickupFrom
     *
     * @return void
     */
    public function setPickupFrom(?\DateTimeInterface $pickupFrom): void
    {
        $this->setData(static::FIELD_PICKUP_FROM, $pickupFrom);
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getPickupTo(): ?\DateTimeInterface
    {
        return $this->getData(static::FIELD_PICKUP_TO);
    }

    /**
     * @param \DateTimeInterface|null $pickupTo
     *
     * @return void
     */
    public function setPickupTo(?\DateTimeInterface $pickupTo): void
    {
        $this->setData(static::FIELD_PICKUP_TO, $pickupTo);
    }

    /**
     * @return bool
     */
    public function isPickupNoCourierOrder(): bool
    {
        return $this->getData(static::FIELD_PICKUP_NO_COURIER_ORDER);
    }

    /**
     * @param bool $value
     *
     * @return void
     */
    public function setPickupNoCourierOrder(bool $value): void
    {
        $this->setData(static::FIELD_PICKUP_NO_COURIER_ORDER, $value);
    }

    /**
     * @return string|null
     */
    public function getNumber(): ?string
    {
        return $this->getExtId();
    }

    /**
     * @param string|null $number
     *
     * @return void
     */
    public function setNumber(?string $number): void
    {
        $this->setExtId($number);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->getData(static::FIELD_STATUS);
    }

    /**
     * @param string $status
     *
     * @return void
     */
    public function setStatus(string $status): void
    {
        $this->setData(static::FIELD_STATUS, $status);
    }

    /**
     * @return string
     */
    public function getStatusCode(): string
    {
        return $this->getData(static::FIELD_STATUS_CODE);
    }

    /**
     * @param string $statusCode
     *
     * @return void
     */
    public function setStatusCode(string $statusCode): void
    {
        $this->setData(static::FIELD_STATUS_CODE, $statusCode);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getDate(): \DateTimeInterface
    {
        return $this->getExtCreatedAt();
    }

    /**
     * @param \DateTimeInterface $date
     *
     * @return void
     */
    public function setDate(\DateTimeInterface $date): void
    {
        $this->setExtCreatedAt($date);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getStatusDate(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_STATUS_DATE);
    }

    /**
     * @param \DateTimeInterface $statusDate
     *
     * @return void
     */
    public function setStatusDate(\DateTimeInterface $statusDate): void
    {
        $this->setData(static::FIELD_STATUS_DATE, $statusDate);
    }

    /**
     * @return ShipmentTypeEnum
     */
    public function getShipmentType(): ShipmentTypeEnum
    {
        return ShipmentTypeEnum::from($this->getData(static::FIELD_SHIPMENT_TYPE));
    }

    /**
     * @param ShipmentTypeEnum $shipmentType
     *
     * @return void
     */
    public function setShipmentType(ShipmentTypeEnum $shipmentType): void
    {
        $this->setData(static::FIELD_SHIPMENT_TYPE, $shipmentType->value);
    }

    /**
     * @return string
     */
    public function getCourier(): string
    {
        return $this->getCarrierCode();
    }

    /**
     * @param string $courier
     *
     * @return void
     */
    public function setCourier(string $courier): void
    {
        $this->setCarrierCode($courier);
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->getData(static::FIELD_DESCRIPTION);
    }

    /**
     * @param string|null $description
     *
     * @return void
     */
    public function setDescription(?string $description): void
    {
        $this->setData(static::FIELD_DESCRIPTION, $description);
    }

    /**
     * @return float
     */
    public function getPriceNet(): float
    {
        return (float)$this->getData(static::FIELD_PRICE_NET);
    }

    /**
     * @param float $priceNet
     *
     * @return void
     */
    public function setPriceNet(float $priceNet): void
    {
        $this->setData(static::FIELD_PRICE_NET, $priceNet);
    }

    /**
     * @return float
     */
    public function getPriceGross(): float
    {
        return $this->getData(static::FIELD_PRICE_GROSS);
    }

    /**
     * @param float $priceGross
     *
     * @return void
     */
    public function setPriceGross(float $priceGross): void
    {
        $this->setData(static::FIELD_PRICE_GROSS, $priceGross);
    }

    /**
     * @return float
     */
    public function getUnpaidAmount(): float
    {
        return (float)$this->getData(static::FIELD_UNPAID_AMOUNT);
    }

    /**
     * @param float $unpaidAmount
     *
     * @return void
     */
    public function setUnpaidAmount(float $unpaidAmount): void
    {
        $this->setData(static::FIELD_UNPAID_AMOUNT, $unpaidAmount);
    }

    /**
     * @return bool
     */
    public function isPaid(): bool
    {
        return (bool)$this->getData(static::FIELD_PAID);
    }

    /**
     * @param bool $paid
     *
     * @return void
     */
    public function setPaid(bool $paid): void
    {
        $this->setData(static::FIELD_PAID, $paid);
    }

    /**
     * @return bool
     */
    public function hasInvoice(): bool
    {
        return $this->getData(static::FIELD_INVOICE);
    }

    /**
     * @param bool $invoice
     *
     * @return void
     */
    public function setInvoice(bool $invoice): void
    {
        $this->setData(static::FIELD_INVOICE, $invoice);
    }

    /**
     * @return float|null
     */
    public function getInsuranceAmount(): ?float
    {
        return $this->getData(static::FIELD_INSURANCE_AMOUNT);
    }

    /**
     * @param float|null $insuranceAmount
     *
     * @return void
     */
    public function setInsuranceAmount(?float $insuranceAmount): void
    {
        $this->setData(static::FIELD_INSURANCE_AMOUNT, $insuranceAmount);
    }

    /**
     * @return string[]|null
     */
    public function getCourierService(): ?array
    {
        return $this->getData(static::FIELD_COURIER_SERVICE)
            ? \explode(',', (string)$this->getData(static::FIELD_COURIER_SERVICE))
            : null;
    }

    /**
     * @param string[]|null $courierService
     *
     * @return void
     */
    public function setCourierService(?array $courierService): void
    {
        $this->setData(static::FIELD_COURIER_SERVICE, \implode(',', $courierService));
    }

    /**
     * @return PickupInterface
     */
    public function getPickup(): PickupInterface
    {
        // TODO
        return $this->pickup;
    }

    /**
     * @param PickupInterface $pickup
     *
     * @return void
     */
    public function setPickup(PickupInterface $pickup): void
    {
        // TODO
        $this->pickup = $pickup;
    }

    /**
     * @return string|null
     */
    public function getTrackingUri(): ?string
    {
        return $this->getData(static::FIELD_TRACKING_URI);
    }

    /**
     * @param string|null $trackingUri
     *
     * @return void
     */
    public function setTrackingUri(?string $trackingUri): void
    {
        $this->setData(static::FIELD_TRACKING_URI, $trackingUri);
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getDeliveredDate(): ?\DateTimeInterface
    {
        return $this->getData(static::FIELD_DELIVERED_AT);
    }

    /**
     * @param \DateTimeInterface|null $deliveredDate
     *
     * @return void
     */
    public function setDeliveredDate(?\DateTimeInterface $deliveredDate): void
    {
        $this->setData(static::FIELD_DELIVERED_AT, $deliveredDate);
    }

    /**
     * @return array|null
     */
    public function getAdditionalFields(): ?array
    {
        return $this->getData(static::FIELD_ADDITIONAL_FIELDS);
    }

    /**
     * @param array|null $additionalFields
     *
     * @return void
     */
    public function setAdditionalFields(?array $additionalFields): void
    {
        $this->setData(static::FIELD_ADDITIONAL_FIELDS, $additionalFields);
    }

    /**
     * @return AddressInterface
     */
    public function getSender(): BaseAddressInterface
    {
        if (!$this->addressesLoaded) {
            $this->loadAddresses();
        }

        return $this->sender;
    }

    /**
     * @param AddressInterface $sender
     *
     * @return void
     */
    public function setSender(BaseAddressInterface $sender): void
    {
        $this->sender = $sender;
        $this->sender->setOrder($this);
    }

    /**
     * @return AddressInterface
     */
    public function getRecipient(): BaseAddressInterface
    {
        if (!$this->addressesLoaded) {
            $this->loadAddresses();
        }

        return $this->recipient;
    }

    /**
     * @param AddressInterface $recipient
     *
     * @return void
     */
    public function setRecipient(BaseAddressInterface $recipient): void
    {
        $this->recipient = $recipient;
        $this->recipient->setOrder($this);
    }

    /**
     * @return AddressInterface|null
     */
    public function getCoverAddress(): ?BaseAddressInterface
    {
        if (!$this->addressesLoaded) {
            $this->loadAddresses();
        }

        return $this->coverAddress;
    }

    /**
     * @param AddressInterface|null $coverAddress
     *
     * @return void
     */
    public function setCoverAddress(?BaseAddressInterface $coverAddress): void
    {
        $this->coverAddress = $coverAddress;
        $this->coverAddress->setOrder($this);
    }

    /**
     * @return CodInterface|null
     */
    public function getCod(): ?BaseCodInterface
    {
        return $this->cod;
    }

    /**
     * @param CodInterface|null $cod
     *
     * @return void
     */
    public function setCod(?BaseCodInterface $cod): void
    {
        $this->cod = $cod;
        if ($this->getOrderId()) {
            $this->cod?->setOrderId($this->getOrderId());
        }
    }

    /**
     * @return iterable<ItemInterface>
     */
    public function getItems(): iterable
    {
        if (!$this->items) {
            $this->items = $this->loadItems();
        }

        return $this->items;
    }

    /**
     * @param iterable<ItemInterface> $items
     *
     * @return void
     */
    public function setItems(iterable $items): void
    {
        $this->items = $items;
    }

    /**
     * @return iterable<PackInterface>
     */
    public function getPacks(): iterable
    {
        if (!$this->packs) {
            $this->packs = $this->loadPacks();
        }

        return $this->packs;
    }

    /**
     * @param iterable<PackInterface> $packs
     *
     * @return void
     */
    public function setPacks(iterable $packs): void
    {
        $this->packs = $packs;
    }

    /**
     * @return iterable<WaybillInterface>
     */
    public function getWaybills(): iterable
    {
        if (!$this->waybills) {
            $this->waybills = $this->loadWaybills();
        }

        return $this->waybills;
    }

    /**
     * @param iterable<WaybillInterface> $waybills
     *
     * @return void
     */
    public function setWaybills(iterable $waybills): void
    {
        $this->waybills = $waybills;
    }

    /**
     * @return void
     */
    private function loadAddresses(): void
    {
        if (!$this->getOrderId()) {
            return;
        }
        $addresses = $this->addressCollectionFactory->create()
            ->addOrderFilter($this->getOrderId());
        /** @var AddressInterface $address */
        foreach ($addresses as $address) {
            match ($address->getType()) {
                AddressTypeEnum::RECIPIENT => $this->setRecipient($address),
                AddressTypeEnum::SENDER => $this->setSender($address),
                AddressInterface::TYPE_COVER => $this->setCoverAddress($address)
            };
        }

        if (!$this->sender) {
            $this->sender = $this->addressFactory->create();
        }

        if (!$this->recipient) {
            $this->recipient = $this->addressFactory->create();
        }

        $this->addressesLoaded = true;
    }

    /**
     * @return ItemCollection
     */
    private function loadItems(): ItemCollection
    {
        return $this->itemCollectionFactory->create()->addOrderFilter($this->getOrderId());
    }

    /**
     * @return PackCollection
     */
    private function loadPacks(): PackCollection
    {
        return $this->packCollectionFactory->create()->addOrderFilter($this->getOrderId());
    }

    /**
     * @return WaybillCollection
     */
    private function loadWaybills(): WaybillCollection
    {
        return $this->waybillCollectionFactory->create()->addOrderFilter($this->getOrderId())->sortDefault();
    }
}
