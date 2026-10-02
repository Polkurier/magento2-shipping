<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\ViewModel\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PickupMode;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\NativeLabel;
use Polkurier\Shipping\Model\Shipment\NotPlaced;
use Polkurier\Shipping\Model\Shipment\OrderAmounts;
use Polkurier\Shipping\Model\Shipment\PackKinds;
use Polkurier\Shipping\Model\Shipment\PlnAmounts;
use Polkurier\Shipping\Model\Shipment\ScreenContext;
use Polkurier\Shipping\Model\Shipment\SendAgain;
use Polkurier\Shipping\Model\Shipment\ShipmentOptions;

/**
 * The Polkurier options of a shipping label, on Magento's New Shipment page, and on the page of a
 * Magento shipment without a Polkurier order that stands (its order was cancelled, or it was made
 * without one), where "Create Shipping Label" places one for its items.
 *
 * Each group of options shows as a one-line summary and is edited in its own modal. The values
 * travel with the shipment form as hidden fields, and are read when Magento asks for the label.
 * The page is Magento's own, with no UI component to hook into, so a template with this view
 * model renders them; what it starts from is shared with the shipment screen (ScreenContext).
 */
class LabelOptions implements ArgumentInterface
{
    private ?Order $order = null;
    private ?MagentoShipmentModel $existing = null;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly AuthorizationInterface $authorization,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ScreenContext $context,
        private readonly ShipmentOptions $options,
        private readonly Config $config,
        private readonly AdditionalService $services,
        private readonly CodType $codTypes,
        private readonly CodReturnType $codReturnTypes,
        private readonly PickupMode $pickupModes,
        private readonly Json $json,
        private readonly OrderAmounts $amounts,
        private readonly OrderPointStore $pointStore,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly MagentoShipment $magentoShipment,
        private readonly SendAgain $sendAgain,
        private readonly TimezoneInterface $timezone,
        private readonly NotPlaced $notPlaced,
        private readonly UrlInterface $url,
    ) {}

    /**
     * Only for orders placed with a Polkurier method, which Magento creates labels for with this
     * carrier.
     */
    public function isAvailable(): bool
    {
        $order = $this->getOrder();
        $existing = $this->getExistingShipment();

        return $order !== null
            && $this->isPolkurierOrder()
            && $this->config->isNativeLabelsEnabled((int)$order->getStoreId())
            && $this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
            // On a shipment's page: one that can take a Polkurier order, and has no label yet.
            && ($existing === null || (!$existing->getShippingLabel()
                && isset($this->magentoShipment->getFreeShipments($order)[(int)$existing->getId()])));
    }

    /**
     * Whether the page's order was placed with a Polkurier method: its packaging window, whatever
     * else, gets the pack templates.
     */
    public function isPolkurierOrder(): bool
    {
        $order = $this->getOrder();

        return $order !== null && Polkurier::methodCodeOf((string)$order->getShippingMethod()) !== null;
    }

    /**
     * What the packaging window needs: the pack templates, and how a parcel is weighed.
     */
    public function getPackagingConfig(): string
    {
        $order = $this->getOrder();

        return (string)$this->json->serialize([
            'templates' => $this->context->getTemplates(),
            // What each parcel type makes the shipment, for the packages' check.
            'packShipmentTypes' => (object)PackKinds::map(),
            // Services for pallets, which only pallets may have.
            'palletServices' => \array_map(static fn($service): string => $service->value, CarrierFeatures::PALLET_SERVICES),
            'parcelWeight' => $order !== null ? $this->context->getParcelWeight((int)$order->getStoreId()) : 'products',
            'autoPack' => $this->getAutoPack(),
        ]);
    }

    /**
     * Packing the items into the pack templates, as the Polkurier shipment screen does: on asking,
     * and when the packaging window opens empty if new shipments start packed.
     *
     * @return array{url: string, orderId: int, shipmentId: int|null, onOpen: bool}|null null where
     *     no label is created with the options
     */
    private function getAutoPack(): ?array
    {
        $order = $this->getOrder();
        if ($order === null || !$this->isAvailable()) {
            return null;
        }
        $existing = $this->getExistingShipment();

        return [
            'url' => $this->url->getUrl('polkurier/shipment/pack'),
            'orderId' => (int)$order->getEntityId(),
            'shipmentId' => $existing !== null ? (int)$existing->getId() : null,
            'onOpen' => $this->config->isPackingNewShipments((int)$order->getStoreId()),
        ];
    }

    /**
     * The order's attempt that wasn't placed and can be sent again, which the label replaces.
     */
    public function getFailedAttempt(): ?string
    {
        $order = $this->getOrder();
        $failed = $order !== null ? $this->sendAgain->findForOrder($order) : null;
        if ($failed === null) {
            return null;
        }

        return (string)\__(
            'A shipment of this order wasn\'t placed on %1: %2. Creating the label places it, as filled in here. Unless placed or discarded, it\'s removed on %3.',
            $this->formatUtc((string)$failed->getCreatedAt()),
            \rtrim((string)$failed->getLastError(), '. '),
            $this->formatUtc($this->notPlaced->expiresAt($failed)),
        );
    }

    private function formatUtc(string $utc): string
    {
        return $this->timezone->formatDateTime(
            new \DateTimeImmutable($utc, new \DateTimeZone('UTC')),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT,
        );
    }

    /**
     * Whether this is the page of a Magento shipment there is, not the New Shipment page.
     */
    public function isForExistingShipment(): bool
    {
        return $this->getExistingShipment() !== null;
    }

    public function getJsConfig(): string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return '{}';
        }
        $storeId = (int)$order->getStoreId();
        $existing = $this->getExistingShipment();
        $shipped = $existing !== null ? $this->magentoShipment->getShipmentQuantities($order, (int)$existing->getId()) : null;
        $values = $this->context->getDefaultValues($order, $shipped);
        $amounts = $this->context->getAmounts($order);

        return (string)$this->json->serialize([
            'name' => NativeLabel::OPTIONS_PARAM,
            // A shipment there is: its quantities are fixed, and its label is asked for on its page.
            'existingShipment' => $existing !== null,
            'shippedQtys' => (object)($shipped ?? []),
            'fields' => NativeLabel::OPTION_FIELDS,
            'values' => (object)\array_intersect_key($values, \array_flip(NativeLabel::OPTION_FIELDS)),
            'currency' => PlnAmounts::CURRENCY,
            'grandTotal' => $amounts['grand_total'],
            'shippingCharged' => $amounts['shipping_charged'],
            'codCollected' => $amounts['cod_collected'],
            'codRemaining' => $amounts['cod_remaining'],
            'shippingCovered' => $amounts['shipping_covered'],
            'items' => (object)$this->amounts->getItems($order),
            'carriers' => (object)$this->options->getCarrierFeatures(),
            'carriersError' => $this->options->getCarriersError(),
            'map' => $this->context->getMap(),
            'templates' => $this->context->getTemplates(),
            // What each parcel type makes the shipment, for the packages' check.
            'packShipmentTypes' => (object)PackKinds::map(),
            // Services for pallets, which only pallets may have.
            'palletServices' => \array_map(static fn($service): string => $service->value, CarrierFeatures::PALLET_SERVICES),
            'parcelWeight' => $this->context->getParcelWeight($storeId),
            'autoPack' => $this->getAutoPack(),
            // Where the point map starts: the postcode alone, which the city can't contradict.
            'mapSearch' => \trim((string)$order->getShippingAddress()?->getPostcode()),
            // The collection dates and windows, as the Polkurier shipment screen asks for them.
            'pickupTimes' => [
                'url' => $this->url->getUrl('polkurier/shipment/pickupTimes'),
                'orderId' => (int)$order->getEntityId(),
                'recipientPostcode' => \trim((string)$order->getShippingAddress()?->getPostcode()),
            ],
            'point' => $this->describePoint($order, $values),
            'options' => [
                'carrier_code' => $this->options->getCarrierOptions(),
                'sender_address_id' => \array_merge(
                    [['value' => '', 'label' => (string)\__('The store\'s address')]],
                    $this->options->getSenderOptions(),
                ),
                'services' => $this->services->toOptionArray(),
                // Only those that can be printed as the sender.
                'cover_address_id' => \array_merge(
                    [['value' => '', 'label' => (string)\__('None: the sender address')]],
                    $this->options->getSenderOptions(true),
                ),
                'cod_type' => $this->codTypes->toOptionArray(),
                'cod_return_type' => $this->codReturnTypes->toOptionArray(),
                'cod_bank_account_id' => $this->options->getBankAccountOptions(),
                'pickup_mode' => $this->pickupModes->toOptionArray(),
            ],
        ]);
    }

    /**
     * The order's pickup point as Polkurier has it now: its address today, or the one the
     * customer chose it by when it can't be looked up, and whether it's closed or gone since.
     *
     * @param array<string, mixed> $values
     * @return array{id: string, label: string, closed: bool}
     */
    private function describePoint(Order $order, array $values): array
    {
        $pointId = (string)($values['recipient_point_id'] ?? '');
        $point = $this->context->findRecipientPoint((string)($values['carrier_code'] ?? ''), $pointId);
        if ($point) {
            $label = (string)$point->getAddress();
        } else {
            $chosen = $pointId === '' ? null : $this->pointStore->getForOrder((int)$order->getEntityId());
            $label = $chosen !== null && $chosen->getPointId() === $pointId ? (string)$chosen->getPointLabel() : '';
        }

        return ['id' => $pointId, 'label' => $label, 'closed' => $point === false];
    }

    /**
     * The Magento shipment whose page this is, when it isn't the New Shipment page.
     */
    private function getExistingShipment(): ?MagentoShipmentModel
    {
        $shipmentId = (int)$this->request->getParam('shipment_id');
        if ($this->existing === null && $shipmentId) {
            try {
                $shipment = $this->shipmentRepository->get($shipmentId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                return null;
            }
            $this->existing = $shipment instanceof MagentoShipmentModel ? $shipment : null;
        }

        return $this->existing;
    }

    private function getOrder(): ?Order
    {
        if ($this->order === null) {
            $orderId = (int)$this->request->getParam('order_id') ?: (int)$this->getExistingShipment()?->getOrderId();
            if (!$orderId) {
                return null;
            }
            try {
                $order = $this->orderRepository->get($orderId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                return null;
            }
            $this->order = $order instanceof Order ? $order : null;
        }

        return $this->order;
    }
}
