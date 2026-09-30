<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\ViewModel\Adminhtml;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PickupMode;
use Polkurier\Shipping\Model\Config\Source\ShipmentType;
use Polkurier\Shipping\Model\Shipment\NativeLabel;
use Polkurier\Shipping\Model\Shipment\OrderAmounts;
use Polkurier\Shipping\Model\Shipment\PlnAmounts;
use Polkurier\Shipping\Model\Shipment\ScreenContext;
use Polkurier\Shipping\Model\Shipment\ShipmentOptions;

/**
 * The Polkurier options of a shipping label, on Magento's New Shipment page.
 *
 * Each group of options shows as a one-line summary and is edited in its own modal. The values
 * travel with the shipment form as hidden fields, and are read when Magento asks for the label.
 * The page is Magento's own, with no UI component to hook into, so a template with this view
 * model renders them; what it starts from is shared with the shipment screen (ScreenContext).
 */
class LabelOptions implements ArgumentInterface
{
    private ?Order $order = null;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly AuthorizationInterface $authorization,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ScreenContext $context,
        private readonly ShipmentOptions $options,
        private readonly Config $config,
        private readonly ShipmentType $shipmentTypes,
        private readonly AdditionalService $services,
        private readonly CodType $codTypes,
        private readonly CodReturnType $codReturnTypes,
        private readonly PickupMode $pickupModes,
        private readonly Json $json,
        private readonly OrderAmounts $amounts,
    ) {}

    /**
     * Only for orders placed with a Polkurier method, which Magento creates labels for with this
     * carrier.
     */
    public function isAvailable(): bool
    {
        $order = $this->getOrder();

        return $order !== null
            && Polkurier::methodCodeOf((string)$order->getShippingMethod()) !== null
            && $this->config->isNativeLabelsEnabled((int)$order->getStoreId())
            && $this->authorization->isAllowed('Polkurier_Shipping::shipment_create');
    }

    public function getJsConfig(): string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return '{}';
        }
        $storeId = (int)$order->getStoreId();
        $values = $this->context->getDefaultValues($order);
        $amounts = $this->context->getAmounts($order);

        return (string)$this->json->serialize([
            'name' => NativeLabel::OPTIONS_PARAM,
            'fields' => NativeLabel::OPTION_FIELDS,
            'values' => (object)\array_intersect_key($values, \array_flip(NativeLabel::OPTION_FIELDS)),
            'currency' => PlnAmounts::CURRENCY,
            'grandTotal' => $amounts['grand_total'],
            'codCollected' => $amounts['cod_collected'],
            'codRemaining' => $amounts['cod_remaining'],
            'items' => (object)$this->amounts->getItems($order),
            'carriers' => (object)$this->options->getCarrierFeatures(),
            'carriersError' => $this->options->getCarriersError(),
            'map' => $this->context->getMap(),
            'templates' => $this->context->getTemplates(),
            'parcelWeight' => $this->context->getParcelWeight($storeId),
            'mapSearch' => \trim($order->getShippingAddress()?->getPostcode() . ' ' . $order->getShippingAddress()?->getCity()),
            'options' => [
                'carrier_code' => $this->options->getCarrierOptions(),
                'shipment_type' => $this->shipmentTypes->toOptionArray(),
                'sender_address_id' => \array_merge(
                    [['value' => '', 'label' => (string)\__('The store\'s address')]],
                    $this->options->getSenderOptions(),
                ),
                'services' => $this->services->toOptionArray(),
                'cod_type' => $this->codTypes->toOptionArray(),
                'cod_return_type' => $this->codReturnTypes->toOptionArray(),
                'cod_bank_account_id' => $this->options->getBankAccountOptions(),
                'pickup_mode' => $this->pickupModes->toOptionArray(),
            ],
        ]);
    }

    private function getOrder(): ?Order
    {
        if ($this->order === null) {
            $orderId = (int)$this->request->getParam('order_id');
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
