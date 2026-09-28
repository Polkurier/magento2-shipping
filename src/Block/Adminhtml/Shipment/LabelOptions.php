<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Shipment;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PickupMode;
use Polkurier\Shipping\Model\Config\Source\ShipmentType;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\NativeLabel;
use Polkurier\Shipping\Model\Shipment\ShipmentOptions;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The Polkurier options of a shipping label, on Magento's New Shipment page.
 *
 * Each group of options shows as a one-line summary and is edited in its own modal. The values
 * travel with the shipment form as hidden fields, and are read when Magento asks for the label.
 */
class LabelOptions extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Polkurier_Shipping::shipment/label-options.phtml';

    private ?Order $order = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentManagementInterface $management,
        private readonly DraftHydrator $hydrator,
        private readonly ShipmentOptions $options,
        private readonly Config $config,
        private readonly WebsiteScope $websiteScope,
        private readonly ShipmentType $shipmentTypes,
        private readonly AdditionalService $services,
        private readonly CodType $codTypes,
        private readonly CodReturnType $codReturnTypes,
        private readonly PickupMode $pickupModes,
        private readonly Json $json,
        private readonly MapTokenProvider $mapTokenProvider,
        private readonly MasterData $masterData,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

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
            && $this->_authorization->isAllowed('Polkurier_Shipping::shipment_create');
    }

    public function getJsConfig(): string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return '{}';
        }
        $storeId = (int)$order->getStoreId();
        $websiteId = $this->websiteScope->getWebsiteId($storeId);
        try {
            $values = $this->hydrator->toArray($this->management->getDefaultDraft((int)$order->getEntityId()));
        } catch (LocalizedException) {
            $values = [];
        }

        return $this->json->serialize([
            'name' => NativeLabel::OPTIONS_PARAM,
            'fields' => NativeLabel::OPTION_FIELDS,
            'values' => (object)\array_intersect_key($values, \array_flip(NativeLabel::OPTION_FIELDS)),
            'currency' => $order->getOrderCurrencyCode(),
            'grandTotal' => \round((float)$order->getGrandTotal(), 2),
            'carriers' => (object)$this->options->getCarrierFeatures($storeId),
            'map' => ['token' => $this->getMapToken($storeId), 'baseUrl' => $this->config->getMapUrl($storeId) . '/'],
            'templates' => $this->getTemplates($websiteId),
            'mapSearch' => \trim($order->getShippingAddress()?->getPostcode() . ' ' . $order->getShippingAddress()?->getCity()),
            'options' => [
                'carrier_code' => $this->options->getCarrierOptions($storeId),
                'shipment_type' => $this->shipmentTypes->toOptionArray(),
                'sender_address_id' => \array_merge(
                    [['value' => '', 'label' => (string)\__('The store\'s address')]],
                    $this->options->getSenderOptions($websiteId),
                ),
                'services' => $this->services->toOptionArray(),
                'cod_type' => $this->codTypes->toOptionArray(),
                'cod_return_type' => $this->codReturnTypes->toOptionArray(),
                'cod_bank_account_id' => $this->options->getBankAccountOptions($websiteId),
                'pickup_mode' => $this->pickupModes->toOptionArray(),
            ],
        ]);
    }

    /**
     * The website's pack templates, for filling in a package in the packaging window.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTemplates(int $websiteId): array
    {
        $templates = [];
        foreach ($this->masterData->getPackTemplates($websiteId) as $template) {
            $templates[] = [
                'id' => (string)$template->getId(),
                'name' => $template->getName(),
                'pack_type' => $template->getPackType(),
                'length' => $template->getLength(),
                'width' => $template->getWidth(),
                'height' => $template->getHeight(),
                'weight' => $template->getWeight(),
            ];
        }

        return $templates;
    }

    private function getMapToken(int $storeId): string
    {
        try {
            return $this->mapTokenProvider->getToken($storeId);
        } catch (PolkurierException) {
            return '';
        }
    }

    private function getOrder(): ?Order
    {
        if ($this->order === null) {
            $orderId = (int)$this->getRequest()->getParam('order_id');
            if (!$orderId) {
                return null;
            }
            try {
                $order = $this->orderRepository->get($orderId);
            } catch (\Exception) {
                return null;
            }
            $this->order = $order instanceof Order ? $order : null;
        }

        return $this->order;
    }

    protected function _toHtml(): string
    {
        return $this->isAvailable() ? parent::_toHtml() : '';
    }
}
