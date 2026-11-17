<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\ViewModel\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PickupMode;
use Polkurier\Shipping\Model\PickupPoint\AdminMapConfigProvider;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Screen\ScreenDataProvider;
use Polkurier\Shipping\Model\Shipment\Screen\ShipmentOptionsProvider;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Webservice\Entity\Order as RemoteOrder;

/**
 * The Polkurier options of a shipping label, on Magento's New Shipment page, and on the page of a
 * Magento shipment without an active Polkurier order (its order was cancelled, or it was made
 * without one), where "Create Shipping Label" places one for its items.
 *
 * Each group of options shows as a one-line summary and is edited in place, in its line. The values
 * travel with the shipment form as hidden fields, and are read when Magento asks for the label.
 * The page is Magento's own, with no UI component to hook into, so a template with this view model
 * renders them; the defaults it starts from are shared with the shipment screen ({@see ScreenDataProvider}).
 */
class LabelOptions implements ArgumentInterface, ResetAfterRequestInterface
{
    private ?Order $order = null;
    private ?MagentoShipmentModel $existing = null;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly AuthorizationInterface $authorization,
        private readonly OrderLoader $orderLoader,
        private readonly ScreenDataProvider $screenDataProvider,
        private readonly ShipmentOptionsProvider $shipmentOptionsProvider,
        private readonly ConfigProvider $configProvider,
        private readonly AdditionalService $services,
        private readonly CodType $codTypes,
        private readonly CodReturnType $codReturnTypes,
        private readonly PickupMode $pickupModes,
        private readonly Json $json,
        private readonly OrderAmountCalculator $orderAmountCalculator,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly NativeShipmentService $nativeShipmentService,
        private readonly RetriableShipmentFinder $retriableShipmentFinder,
        private readonly FailedShipmentManager $failedShipmentManager,
        private readonly UrlInterface $url,
        private readonly AdminMapConfigProvider $adminMapConfigProvider,
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
            && $this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
            // On a shipment's page: one that can take a Polkurier order, and has no label yet.
            && ($existing === null || (!$existing->getShippingLabel()
                && isset($this->nativeShipmentService->getFreeShipments($order)[(int)$existing->getId()])));
    }

    /**
     * Whether the page's order was placed with a Polkurier method. If so, its packaging window gets
     * the pack templates in any case.
     */
    public function isPolkurierOrder(): bool
    {
        $order = $this->getOrder();

        return $order !== null && Polkurier::isOrderOf($order);
    }

    /**
     * What the packaging window needs: the pack templates, and how a parcel is weighed.
     */
    public function getPackagingConfig(): string
    {
        $order = $this->getOrder();

        return (string)$this->json->serialize([
            'templates' => $this->screenDataProvider->getTemplates(),
            'parcelWeight' => $order !== null
                ? $this->configProvider->getParcelWeight((int)$order->getStoreId())->value
                : 'products',
            'autoPack' => $this->getAutoPack(),
        ]);
    }

    /**
     * The order's attempt that wasn't placed and can be sent again, which the label replaces.
     */
    public function getFailedAttempt(): ?string
    {
        $order = $this->getOrder();
        $failed = $order !== null ? $this->retriableShipmentFinder->findForOrder($order) : null;
        if ($failed === null) {
            return null;
        }

        $described = $this->failedShipmentManager->describe($failed);

        return (string)__(
            'A shipment of this order wasn\'t placed on %1: %2. Creating the label places it, as filled in here. '
            . 'Unless placed or discarded, it\'s removed on %3.',
            $described['tried'],
            $described['reason'],
            $described['removed'],
        );
    }

    /**
     * Whether this is the page of an existing Magento shipment, not the New Shipment page.
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
        $shipped = $existing !== null
            ? $this->nativeShipmentService->getShipmentQuantities($order, (int)$existing->getId())
            : null;
        $values = $this->screenDataProvider->getDefaultValues($order, $shipped);
        $amounts = $this->screenDataProvider->getAmounts($order);
        $point = $this->screenDataProvider->describeRecipientPoint(
            (int)$order->getEntityId(),
            (string)($values[ShipmentDraftInterface::FIELD_CARRIER_CODE] ?? ''),
            (string)($values[ShipmentDraftInterface::FIELD_RECIPIENT_POINT_ID] ?? ''),
        );

        return (string)$this->json->serialize([
            'name' => NativeLabelService::OPTIONS_PARAM,
            // An existing shipment: its quantities are fixed, and its label is asked for on its page.
            'existingShipment' => $existing !== null,
            'shippedQtys' => (object)($shipped ?? []),
            'fields' => NativeLabelService::OPTION_FIELDS,
            'values' => (object)\array_intersect_key($values, \array_flip(NativeLabelService::OPTION_FIELDS)),
            'currency' => OrderAmountCalculator::CURRENCY,
            'grandTotal' => $amounts['grand_total'],
            'shippingCharged' => $amounts['shipping_charged'],
            'codCollected' => $amounts['cod_collected'],
            'codRemaining' => $amounts['cod_remaining'],
            'shippingCovered' => $amounts['shipping_covered'],
            'items' => (object)$this->orderAmountCalculator->getItems($order),
            'carriers' => (object)$this->shipmentOptionsProvider->getCarrierFeatures(),
            'carriersError' => $this->shipmentOptionsProvider->getCarriersError(),
            'map' => $this->adminMapConfigProvider->getMapConfig(true),
            // The shipment type each parcel type makes, for checking the packages.
            'packShipmentTypes' => (object)ParcelTypeMapper::getShipmentTypeMap(),
            // Services for pallets, which only pallets may have.
            'palletServices' => $this->screenDataProvider->getPalletServices(),
            // Where the point map starts: the postcode alone, which the city can't contradict.
            'mapSearch' => \trim((string)$order->getShippingAddress()?->getPostcode()),
            // Where the drop-off point's map starts for each sender address.
            'senderPostcodes' => (object)$this->screenDataProvider->getSenderPostcodes(),
            // The collection dates and windows, as the Polkurier shipment screen asks for them.
            'pickupTimes' => [
                'url' => $this->url->getUrl('polkurier/shipment/pickupTimes'),
                'orderId' => (int)$order->getEntityId(),
                'recipientPostcode' => \trim((string)$order->getShippingAddress()?->getPostcode()),
            ],
            'point' => $point,
            'description' => [
                'maxLength' => RemoteOrder::DESCRIPTION_MAX_LENGTH,
                'notice' => $this->screenDataProvider->getDescriptionNotice(),
            ],
            // Names and addresses by point code; points chosen on the map are added to them.
            'pointDetails' => (object)$this->screenDataProvider->describePoints(
                $point,
                (string)($values[ShipmentDraftInterface::FIELD_SENDER_POINT_ID] ?? ''),
            ),
            'options' => [
                ShipmentDraftInterface::FIELD_CARRIER_CODE => $this->shipmentOptionsProvider->getCarrierOptions(),
                ShipmentDraftInterface::FIELD_SENDER_ADDRESS_ID
                    => $this->shipmentOptionsProvider->getSenderChoices($storeId),
                ShipmentDraftInterface::FIELD_SERVICES => $this->services->toOptionArray(),
                // Only those that can be printed as the sender.
                ShipmentDraftInterface::FIELD_COVER_ADDRESS_ID => \array_merge(
                    [['value' => '', 'label' => (string)__('None: the sender address')]],
                    $this->shipmentOptionsProvider->getSenderOptions(true),
                ),
                ShipmentDraftInterface::FIELD_COD_TYPE => $this->codTypes->toOptionArray(),
                ShipmentDraftInterface::FIELD_COD_RETURN_TYPE => $this->codReturnTypes->toOptionArray(),
                ShipmentDraftInterface::FIELD_COD_BANK_ACCOUNT_ID
                    => $this->shipmentOptionsProvider->getBankAccountChoices(),
                ShipmentDraftInterface::FIELD_PICKUP_MODE => $this->pickupModes->toOptionArray(),
            ],
        ]);
    }

    public function _resetState(): void
    {
        $this->order = null;
        $this->existing = null;
    }

    /**
     * Settings for packing the items into the pack templates, as the Polkurier shipment screen
     * does: on request, and when the packaging window opens empty if new shipments start packed.
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
            'onOpen' => $this->configProvider->isPackingNewShipments((int)$order->getStoreId()),
        ];
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
            $this->order = $this->orderLoader->find($orderId);
        }

        return $this->order;
    }
}
