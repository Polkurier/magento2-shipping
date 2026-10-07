<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Draft;

use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\DraftFactory;
use Polkurier\Shipping\Model\Shipment\Packing\AutoPacker;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Shipping\Model\Shipment\Placement\SenderMapper;
use Polkurier\Shipping\Model\Shipment\Placement\StreetParser;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;
use Psr\Log\LoggerInterface;

/**
 * Prepares the draft a merchant starts from: what the customer ordered, where to, and the defaults
 * configured for the order's store, narrowed to what the carrier supports.
 */
readonly class DefaultDraftBuilder
{
    public function __construct(
        private DraftFactory $draftFactory,
        private PackFactory $packFactory,
        private ConfigProvider $configProvider,
        private AddressRepositoryInterface $addressRepository,
        private BankAccountRepositoryInterface $bankAccountRepository,
        private PackTemplateRepositoryInterface $packTemplateRepository,
        private OrderPointRepositoryInterface $orderPointRepository,
        private StreetParser $streetParser,
        private CarrierCatalog $catalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private OrderAmountCalculator $amountCalculator,
        private ItemFactory $itemFactory,
        private AutoPacker $autoPacker,
        private LoggerInterface $logger,
        private SenderMapper $senderMapper,
    ) {}

    /**
     * @param array<int, float>|null $qtys quantities shipped by order item id, or null for
     *     everything the order still has to ship
     */
    public function createFromOrder(Order $order, ?array $qtys = null): ShipmentDraftInterface
    {
        $storeId = (int)$order->getStoreId();
        $carrierCode = $this->getCarrierCode($order);
        $carrier = $carrierCode !== null ? $this->getCarrier($carrierCode) : null;

        $draft = $this->draftFactory->create();
        $draft->setCarrierCode((string)$carrierCode);
        $draft->setShipmentType($this->getShipmentType($carrier, $storeId));
        $draft->setDescription(
            \sprintf($this->configProvider->getDescriptionFormat($storeId), $order->getIncrementId()),
        );
        $sender = $this->addressRepository->getDefaultSenderAddress();
        $senderId = $sender?->getId();
        $draft->setSenderAddressId($senderId !== null ? (int)$senderId : null);
        // Handed over at the sender's drop-off point, for a carrier that takes parcels at points.
        if ($carrier !== null
            && $this->carrierFeatureResolver->getSenderPointRequirement($carrier) !== PointRequirementEnum::None
        ) {
            $draft->setSenderPointId($sender?->getPointId() ?: null);
        }
        // Nothing to send from, neither an address nor the store's: one is typed in, and kept.
        if ($senderId === null
            && $this->addressRepository->getSenderAddresses() === []
            && $this->senderMapper->fromStore($storeId) === null
        ) {
            $draft->setNewSender(true)
                ->setKeepingNewSender(true)
                ->setNewSenderAddress([AddressInterface::FIELD_COUNTRY => Polkurier::HOME_COUNTRY]);
        }
        $this->setRecipient($draft, $order);
        $draft->setItems($this->createItems($order, $qtys));
        [$packs, $estimated] = $this->createPacks($order, $draft->getItems(), $draft->getShipmentType());
        $draft->setPacks($packs);
        // Packed automatically: the screen asks to check the parcels.
        $draft->setPacksEstimated($estimated);
        $draft->setServices([]);
        $draft->setAdditionalFields([]);

        // The whole order is collected and insured in full. When it ships in parts, each part
        // collects and is insured for its products, the shipping and handling only when asked
        // (unticked to start with), and the part carrying the rest collects what is left.
        $draft->setShippingIncluded(false);
        $paymentMethod = (string)$order->getPayment()?->getMethod();
        $codAmount = \in_array($paymentMethod, $this->configProvider->getCodPaymentMethods($storeId), true)
            ? $this->amountCalculator->getCodFor($order, $qtys, false)
            : 0.0;
        $cod = $codAmount > 0;
        if ($cod) {
            $draft->setCodAmount($codAmount);
            $draft->setCodType($this->configProvider->getDefaultCodType($storeId)->value);
            $draft->setCodReturnType($this->configProvider->getDefaultCodReturnType($storeId)->value);
            $bankAccountId = $this->bankAccountRepository->getPreferred()?->getId();
            $draft->setCodBankAccountId($bankAccountId !== null ? (int)$bankAccountId : null);
            // No account to pay out to: one is typed in, and kept.
            if ($bankAccountId === null) {
                $draft->setNewBankAccount(true)->setKeepingNewBankAccount(true);
            }
        }
        $insuranceRequired = $cod && $carrier !== null
            && $this->carrierFeatureResolver->isInsuranceRequiredWithCod($carrier);
        if ($this->configProvider->isInsureByDefault($storeId) || $insuranceRequired) {
            // What it collects, or else what it's worth: either way the order's parts add up to its
            // total. 0 when the order's currency can't be given in złoty: left for the merchant.
            $value = $codAmount > 0 ? $codAmount : $this->amountCalculator->getShipmentValue($order, $qtys, false);
            if ($value > 0) {
                $draft->setInsuranceAmount($value);
            }
        }

        $offered = $carrier !== null ? $this->carrierFeatureResolver->getServices($carrier) : [];
        $draft->setServices(\array_values(\array_map(
            static fn($service): string => $service->value,
            \array_filter(
                $this->configProvider->getDefaultServices($storeId),
                // The cover sender address comes with the cover address below, not as a service;
                // services for pallets go with pallets only.
                static fn($service): bool => \in_array($service, $offered, true)
                    && $service !== AdditionalServiceEnum::CoverSenderAddress
                    && (
                        $draft->getShipmentType() === ShipmentTypeEnum::Palette->value
                        || !\in_array($service, CarrierFeatureResolver::PALLET_SERVICES, true)
                    ),
            ),
        )));
        $draft->setCoverAddressId($this->getCoverAddressId(
            $storeId,
            $carrier === null || \in_array(AdditionalServiceEnum::CoverSenderAddress, $offered, true),
        ));

        // Dropped off where the carrier's default says, or at the sender's drop-off point.
        $draft->setPickupMode(
            $carrier !== null && $this->carrierFeatureResolver->isDropOffAvailable($carrier)
                && ($this->carrierFeatureResolver->isDropOffDefault($carrier) || $draft->getSenderPointId() !== null)
                ? ShipmentDraftInterface::PICKUP_DROP_OFF
                : ShipmentDraftInterface::PICKUP_COURIER,
        );

        return $draft;
    }

    /**
     * The items shipped: the quantities given, or everything the order still has to ship.
     *
     * @param array<int, float>|null $qtys
     *
     * @return Draft\Item[]
     */
    private function createItems(Order $order, ?array $qtys): array
    {
        $items = [];
        foreach ($this->amountCalculator->getItems($order) as $id => $item) {
            $qty = $qtys === null ? $item['qty_to_ship'] : (float)($qtys[$id] ?? 0);
            if ($qty > 0) {
                $items[] = $this->itemFactory->create()->setOrderItemId($id)->setQty($qty);
            }
        }

        return $items;
    }

    /**
     * The carrier of the order's Polkurier method; only such orders are shipped.
     */
    private function getCarrierCode(Order $order): ?string
    {
        return Polkurier::methodCodeOf((string)$order->getShippingMethod());
    }

    private function getCarrier(string $code): ?Carrier
    {
        try {
            return $this->catalog->getCarrier($code);
        } catch (PolkurierException $e) {
            $this->logger->warning('Polkurier carrier list unavailable for the shipment draft', ['exception' => $e]);

            return null;
        }
    }

    /**
     * What the parcels are to be until there are some: as the website's parcels go, or the first
     * kind the carrier takes when it doesn't take that.
     */
    private function getShipmentType(?Carrier $carrier, int $storeId): string
    {
        $default = $this->configProvider->getDefaultShipmentType($storeId);
        if ($carrier === null) {
            return $default->value;
        }
        $types = $this->carrierFeatureResolver->getShipmentTypes($carrier);

        return \in_array($default, $types, true) || $types === [] ? $default->value : $types[0]->value;
    }

    /**
     * The address printed as the sender by default, while it can still be one and the carrier
     * prints one.
     */
    private function getCoverAddressId(int $storeId, bool $offered): ?int
    {
        $id = $this->configProvider->getDefaultCoverAddressId($storeId);
        if ($id === null || !$offered) {
            return null;
        }
        foreach ($this->addressRepository->getSenderAddresses(true) as $address) {
            if ((int)$address->getId() === $id) {
                return $id;
            }
        }

        return null;
    }

    private function setRecipient(ShipmentDraftInterface $draft, Order $order): void
    {
        $address = $order->getShippingAddress() ?? $order->getBillingAddress();
        if ($address === null) {
            return;
        }
        $street = $this->streetParser->parse((array)$address->getStreet());
        $draft->setRecipientPerson(\trim($address->getFirstname() . ' ' . $address->getLastname()));
        $draft->setRecipientCompany($address->getCompany() ?: null);
        $draft->setRecipientStreet($street['street']);
        $draft->setRecipientHouseNumber($street['house_number']);
        $draft->setRecipientFlatNumber($street['flat_number']);
        $draft->setRecipientPostcode((string)$address->getPostcode());
        $draft->setRecipientCity((string)$address->getCity());
        $draft->setRecipientCountry((string)($address->getCountryId() ?: Polkurier::HOME_COUNTRY));
        $draft->setRecipientPhone((string)$address->getTelephone());
        $draft->setRecipientEmail((string)($address->getEmail() ?: $order->getCustomerEmail()));
        $draft->setRecipientPointId(
            $this->orderPointRepository->getForOrder((int)$order->getEntityId())?->getPointId(),
        );
    }

    /**
     * The items packed into parcels of the pack templates, when every product has its shipping
     * dimensions; otherwise one parcel of the default pack template, or of the first of the
     * shipment's kind when the default is of another, or none without one:
     * a parcel nobody sized would only stand in the way.
     *
     * @param \Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface[] $items
     *
     * @return array{0: Draft\Pack[], 1: bool} the parcels, and whether they were packed automatically
     */
    private function createPacks(Order $order, array $items, string $shipmentType): array
    {
        if ($this->configProvider->isPackingNewShipments((int)$order->getStoreId())) {
            $qtys = [];
            foreach ($items as $item) {
                $qtys[$item->getOrderItemId()] = $item->getQty();
            }
            // No parcels yet: packed as the shipment's kind.
            $packed = $this->autoPacker->packByItems($order, $qtys, $shipmentType)['packs'];
            if ($packed !== []) {
                return [$packed, true];
            }
        }
        $template = $this->templateOf($shipmentType);
        if ($template === null) {
            return [[], false];
        }
        // The one parcel carries everything shipped.
        $products = $this->amountCalculator->getItems($order);
        $weight = 0.0;
        $weighed = $items !== [];
        foreach ($items as $item) {
            $unitWeight = (float)($products[$item->getOrderItemId()]['weight'] ?? 0.0);
            $weight += $unitWeight * $item->getQty();
            $weighed = $weighed && $unitWeight > 0;
        }

        return [[$this->packFactory->create()
            ->setTemplateId((int)$template->getId())
            ->setPackType($template->getPackType())
            ->setLength($template->getLength())
            ->setWidth($template->getWidth())
            ->setHeight($template->getHeight())
            ->setWeight($this->configProvider->getParcelWeight((int)$order->getStoreId())->weigh(
                $template->getWeight(),
                $weight,
                $weighed,
                $template->getTare(),
            ))
            ->setAmount(1)], false];
    }

    /**
     * The default pack template, when of the kind given; otherwise the first that is.
     */
    private function templateOf(string $shipmentType): ?PackTemplateInterface
    {
        $default = $this->packTemplateRepository->getDefault();
        if ($default !== null && ParcelTypeMapper::shipmentTypeOf($default->getPackType())->value === $shipmentType) {
            return $default;
        }
        foreach ($this->packTemplateRepository->getAvailable() as $template) {
            if (ParcelTypeMapper::shipmentTypeOf($template->getPackType())->value === $shipmentType) {
                return $template;
            }
        }

        return null;
    }
}
