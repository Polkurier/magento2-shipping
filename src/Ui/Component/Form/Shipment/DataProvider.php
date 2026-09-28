<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Shipment;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Config\Source\PickupMode;
use Polkurier\Shipping\Model\Config\Source\ShipmentType;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\ShipmentOptions;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Data of the Polkurier shipment form: the draft for the order, the options that depend on the
 * order's website, and what each carrier supports, for the form's scripts to act on.
 */
class DataProvider extends AbstractDataProvider
{
    public const string PERSISTOR_KEY = 'polkurier_shipment_form';

    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentManagementInterface $management,
        private readonly DraftHydrator $hydrator,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly MasterData $masterData,
        private readonly ShipmentOptions $options,
        private readonly MapTokenProvider $mapTokenProvider,
        private readonly Config $config,
        private readonly WebsiteScope $websiteScope,
        private readonly UrlInterface $url,
        private readonly ShipmentType $shipmentTypes,
        private readonly PackType $packTypes,
        private readonly CodType $codTypes,
        private readonly CodReturnType $codReturnTypes,
        private readonly AdditionalService $services,
        private readonly PickupMode $pickupModes,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * The form narrows by its own request parameter, not through the collection.
     */
    public function addFilter(Filter $filter): void
    {
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $orderId = (int)$this->request->getParam('order_id');
        if (!$orderId) {
            return $this->loadedData = [];
        }

        $order = $this->orderRepository->get($orderId);
        $storeId = (int)$order->getStoreId();
        try {
            $data = $this->hydrator->toArray($this->management->getDefaultDraft($orderId));
        } catch (LocalizedException) {
            $data = [];
        }
        $persisted = $this->dataPersistor->get(self::PERSISTOR_KEY);
        if (\is_array($persisted) && (int)($persisted['order_id'] ?? 0) === $orderId) {
            $data = \array_merge($data, $persisted);
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
        }

        $data['order_id'] = $orderId;
        $data['order_increment_id'] = $order->getIncrementId();
        $data['store_id'] = $storeId;
        $data['currency'] = $order->getOrderCurrencyCode();
        $data['grand_total'] = (float)$order->getGrandTotal();
        $data['carriers'] = $this->options->getCarrierFeatures($storeId);
        $data['map'] = [
            'token' => $this->getMapToken($storeId),
            'baseUrl' => $this->config->getMapUrl($storeId) . '/',
        ];
        $data['choices'] = $this->getChoices($storeId);
        $data['urls'] = [
            'calculate' => $this->url->getUrl('polkurier/shipment/calculate'),
            'pickup_times' => $this->url->getUrl('polkurier/shipment/pickupTimes'),
        ];

        return $this->loadedData = [$orderId => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $orderId = (int)$this->request->getParam('order_id');
        if (!$orderId) {
            return $meta;
        }
        $storeId = (int)$this->orderRepository->get($orderId)->getStoreId();
        $websiteId = $this->websiteScope->getWebsiteId($storeId);

        $senders = \array_merge(
            [['value' => '', 'label' => (string)\__('The store\'s address')]],
            $this->options->getSenderOptions($websiteId),
        );
        $covers = \array_merge(
            [['value' => '', 'label' => (string)\__('-- None --')]],
            $this->options->getSenderOptions($websiteId, true),
        );
        $accounts = $this->options->getBankAccountOptions($websiteId);
        $templates = [['value' => '', 'label' => (string)\__('-- Custom --')]];
        $templateData = [];
        foreach ($this->masterData->getPackTemplates($websiteId) as $template) {
            $templates[] = ['value' => (string)$template->getId(), 'label' => $template->getName()];
            $templateData[(string)$template->getId()] = [
                'pack_type' => $template->getPackType(),
                'length' => $template->getLength(),
                'width' => $template->getWidth(),
                'height' => $template->getHeight(),
                'weight' => $template->getWeight(),
            ];
        }
        $carriers = \array_merge(
            [['value' => '', 'label' => (string)\__('-- Choose a carrier --')]],
            $this->options->getCarrierOptions($storeId),
        );

        // Paths follow the screen's layout: carriers and parcels on the page, the rest in modals.
        $meta['workspace']['children']['carriers']['children']['carrier_code']['arguments']['data']['config']['options'] = $carriers;
        $meta['shipment_modal']['children']['shipment']['children']['sender_address_id']['arguments']['data']['config']['options'] = $senders;
        $meta['extras_modal']['children']['services']['children']['cover_address_id']['arguments']['data']['config']['options'] = $covers;
        $meta['cod_modal']['children']['cod']['children']['cod_bank_account_id']['arguments']['data']['config']['options'] = $accounts;
        $meta['workspace']['children']['main']['children']['packages']['children']['packs']['children']['record']['children']['template_id']['arguments']['data']['config'] = [
            'options' => $templates,
            'templates' => (object)$templateData,
        ];

        return $meta;
    }

    /**
     * Labels of every choice, by field, for the screen's summaries.
     *
     * @return array<string, object>
     */
    private function getChoices(int $storeId): array
    {
        $websiteId = $this->websiteScope->getWebsiteId($storeId);
        $labels = static function (array $options): object {
            $map = [];
            foreach ($options as $option) {
                $map[(string)$option['value']] = (string)$option['label'];
            }

            return (object)$map;
        };

        return [
            'carrier_code' => $labels($this->options->getCarrierOptions($storeId)),
            'shipment_type' => (object)$this->shipmentTypes->toArray(),
            'pack_type' => (object)$this->packTypes->toArray(),
            'sender_address_id' => $labels(\array_merge(
                [['value' => '', 'label' => (string)\__('The store\'s address')]],
                $this->options->getSenderOptions($websiteId),
            )),
            'cover_address_id' => $labels($this->options->getSenderOptions($websiteId, true)),
            'cod_type' => (object)$this->codTypes->toArray(),
            'cod_return_type' => (object)$this->codReturnTypes->toArray(),
            'cod_bank_account_id' => $labels($this->options->getBankAccountOptions($websiteId)),
            'services' => (object)$this->services->toArray(),
            'pickup_mode' => (object)$this->pickupModes->toArray(),
        ];
    }

    private function getMapToken(int $storeId): string
    {
        try {
            return $this->mapTokenProvider->getToken($storeId);
        } catch (PolkurierException) {
            return '';
        }
    }
}
