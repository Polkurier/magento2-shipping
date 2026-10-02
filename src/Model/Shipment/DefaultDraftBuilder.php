<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\Mapper\StreetParser;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;
use Psr\Log\LoggerInterface;

/**
 * Prepares the draft a merchant starts from: what the customer ordered, where to, and the defaults
 * configured for the order's store, narrowed to what the carrier supports.
 */
class DefaultDraftBuilder
{
    public function __construct(
        private readonly DraftFactory $draftFactory,
        private readonly PackFactory $packFactory,
        private readonly Config $config,
        private readonly MasterData $masterData,
        private readonly OrderPointStore $orderPointStore,
        private readonly StreetParser $streetParser,
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        private readonly OrderAmounts $amounts,
        private readonly ItemFactory $itemFactory,
        private readonly AutoPacker $autoPacker,
        private readonly LoggerInterface $logger,
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
        $draft->setShipmentType($this->getShipmentType($carrier));
        $draft->setDescription(\sprintf($this->config->getDescriptionFormat($storeId), $order->getIncrementId()));
        $senderId = $this->masterData->getDefaultSenderAddress()?->getId();
        $draft->setSenderAddressId($senderId !== null ? (int)$senderId : null);
        $this->setRecipient($draft, $order);
        $draft->setItems($this->createItems($order, $qtys));
        [$packs, $estimated] = $this->createPacks($order, $draft->getItems());
        $draft->setPacks($packs);
        // Packed automatically: the screen asks to check the parcels.
        $draft->setPacksEstimated($estimated);
        $draft->setServices([]);
        $draft->setAdditionalFields([]);

        // The whole order is collected and insured in full. When it ships in parts, each part
        // collects and is insured for its products, the shipping and handling only when asked
        // (unticked to start with), and the part carrying the rest collects what is left.
        $draft->setShippingIncluded(false);
        $codAmount = \in_array((string)$order->getPayment()?->getMethod(), $this->config->getCodPaymentMethods($storeId), true)
            ? $this->amounts->getCodFor($order, $qtys, false)
            : 0.0;
        $cod = $codAmount > 0;
        if ($cod) {
            $draft->setCodAmount($codAmount);
            $draft->setCodType($this->config->getDefaultCodType($storeId)->value);
            $draft->setCodReturnType($this->config->getDefaultCodReturnType($storeId)->value);
            $bankAccountId = $this->masterData->getPreferredBankAccount()?->getId();
            $draft->setCodBankAccountId($bankAccountId !== null ? (int)$bankAccountId : null);
        }
        $insuranceRequired = $cod && $carrier !== null && $this->features->isInsuranceRequiredWithCod($carrier);
        if ($this->config->isInsureByDefault($storeId) || $insuranceRequired) {
            // What it collects, or else what it's worth: either way the order's parts add up to its
            // total. 0 when the order's currency can't be given in złoty: left for the merchant.
            $value = $codAmount > 0 ? $codAmount : $this->amounts->getShipmentValue($order, $qtys, false);
            if ($value > 0) {
                $draft->setInsuranceAmount($value);
            }
        }

        $offered = $carrier !== null ? $this->features->getServices($carrier) : [];
        $draft->setServices(\array_values(\array_map(
            static fn($service): string => $service->value,
            \array_filter(
                $this->config->getDefaultServices($storeId),
                // The cover sender address comes with the cover address below, not as a service;
                // services for pallets go with pallets only.
                static fn($service): bool => \in_array($service, $offered, true) && $service !== AdditionalServiceEnum::CoverSenderAddress
                    && ($draft->getShipmentType() === ShipmentTypeEnum::Palette->value || !\in_array($service, CarrierFeatures::PALLET_SERVICES, true)),
            ),
        )));
        $draft->setCoverAddressId($this->getCoverAddressId($storeId, $carrier === null || \in_array(AdditionalServiceEnum::CoverSenderAddress, $offered, true)));

        $draft->setPickupMode(
            $carrier !== null && $this->features->isDropOffAvailable($carrier) && $this->features->isDropOffDefault($carrier)
                ? ShipmentDraftInterface::PICKUP_DROP_OFF
                : ShipmentDraftInterface::PICKUP_COURIER,
        );

        return $draft;
    }

    /**
     * The items shipped: the quantities given, or everything the order still has to ship.
     *
     * @param array<int, float>|null $qtys
     * @return Draft\Item[]
     */
    private function createItems(Order $order, ?array $qtys): array
    {
        $items = [];
        foreach ($this->amounts->getItems($order) as $id => $item) {
            $qty = $qtys === null ? $item['qty_to_ship'] : (float)($qtys[$id] ?? 0);
            if ($qty > 0) {
                $items[] = $this->itemFactory->create()->setOrderItemId($id)->setQty($qty);
            }
        }

        return $items;
    }

    /**
     * The method the customer chose, or the configured default when the order was placed with
     * another shipping method.
     */
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
     * What the parcels are to be until there are some: the default pack template's kind, a parcel
     * without one, or the first the carrier takes when it doesn't take that.
     */
    private function getShipmentType(?Carrier $carrier): string
    {
        $template = $this->masterData->getDefaultPackTemplate();
        $default = PackKinds::shipmentTypeOf($template?->getPackType());
        if ($carrier === null) {
            return $default->value;
        }
        $types = $this->features->getShipmentTypes($carrier);

        return \in_array($default, $types, true) || $types === [] ? $default->value : $types[0]->value;
    }

    /**
     * The address printed as the sender by default, while it can still be one and the carrier
     * prints one.
     */
    private function getCoverAddressId(int $storeId, bool $offered): ?int
    {
        $id = $this->config->getDefaultCoverAddressId($storeId);
        if ($id === null || !$offered) {
            return null;
        }
        foreach ($this->masterData->getSenderAddresses(true) as $address) {
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
        $draft->setRecipientCountry((string)($address->getCountryId() ?: 'PL'));
        $draft->setRecipientPhone((string)$address->getTelephone());
        $draft->setRecipientEmail((string)($address->getEmail() ?: $order->getCustomerEmail()));
        $draft->setRecipientPointId($this->orderPointStore->getForOrder((int)$order->getEntityId())?->getPointId());
    }

    /**
     * The items packed into parcels of the pack templates, when every product has its shipping
     * dimensions; otherwise one parcel of the default pack template, or none without one:
     * a parcel nobody sized would only stand in the way.
     *
     * @param \Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface[] $items
     * @return array{0: Draft\Pack[], 1: bool} the parcels, and whether they were packed automatically
     */
    private function createPacks(Order $order, array $items): array
    {
        if ($this->config->isPackingNewShipments((int)$order->getStoreId())) {
            $qtys = [];
            foreach ($items as $item) {
                $qtys[$item->getOrderItemId()] = $item->getQty();
            }
            // No parcels yet: the items decide what they go as.
            $packed = $this->autoPacker->packByItems($order, $qtys)['packs'];
            if ($packed !== []) {
                return [$packed, true];
            }
        }
        $template = $this->masterData->getDefaultPackTemplate();
        if ($template === null) {
            return [[], false];
        }
        // The one parcel carries everything shipped.
        $products = $this->amounts->getItems($order);
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
            ->setWeight($this->config->getParcelWeight((int)$order->getStoreId())->weigh($template->getWeight(), $weight, $weighed))
            ->setAmount(1)], false];
    }
}
