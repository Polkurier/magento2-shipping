<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Shipment;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Enum\AdditionalServiceExtensionEnum;
use Polkurier\Shipping\Helper\PaymentHelper;
use Polkurier\Shipping\Helper\ShipmentHelper;
use Polkurier\Shipping\Model\Provider\ConfigProvider;
use Polkurier\Shipping\Model\Provider\Shipment\AdditionalServiceAvailabilityProvider;
use Polkurier\Shipping\Model\Provider\Shipment\DefaultsProvider;
use Polkurier\Shipping\Model\ResourceModel\Order\CollectionFactory;
use Polkurier\Webservice\Enum\AdditionalServiceEnum;

class DataProvider extends AbstractDataProvider
{
    public const string DATA_PERSISTOR_KEY = 'polkurier_order';
    public const string PREFIX_COD_FIELDS = 'cod_';
    public const string PREFIX_PACK_FIELDS = 'pack_';
    public const string CONFIG_SERVICES_AVAILABILITY = 'services_availability';
    public const string CONFIG_PACK_TEMPLATES = 'pack_templates';
    public const string CONFIG_DEFAULT_PACK_TEMPLATE = 'default_pack_template';

    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @param ConfigProvider $configProvider
     * @param PaymentHelper $paymentHelper
     * @param ShipmentHelper $shipmentHelper
     * @param DataPersistorInterface $dataPersistor
     * @param AdditionalServiceAvailabilityProvider $serviceAvailabilityProvider
     * @param PackTemplateRepositoryInterface $packTemplateRepository
     * @param CollectionFactory $collectionFactory
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        /**
         * @var ConfigProvider
         */
        private readonly ConfigProvider $configProvider,

        /**
         * @var PaymentHelper
         */
        private readonly PaymentHelper $paymentHelper,

        /**
         * @var ShipmentHelper
         */
        private readonly ShipmentHelper $shipmentHelper,

        /**
         * @var DataPersistorInterface
         */
        private readonly DataPersistorInterface $dataPersistor,

        /**
         * @var AdditionalServiceAvailabilityProvider
         */
        private readonly AdditionalServiceAvailabilityProvider $serviceAvailabilityProvider,

        /**
         * @var DefaultsProvider
         */
        private readonly DefaultsProvider $defaultsProvider,

        /**
         * @var PackTemplateRepositoryInterface
         */
        private readonly PackTemplateRepositoryInterface $packTemplateRepository,

        CollectionFactory $collectionFactory,
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @return OrderInterface[]
     */
    public function getData(): array
    {
        if ($this->loadedData) {
            return $this->loadedData;
        }

        /** @var OrderInterface $order */
        foreach ($this->collection->getItems() as $order) {
            $this->loadedData[$order->getParentOrderId()] = $this->prepareOrderData($order);
        }

        $persistedData = $this->dataPersistor->get(static::DATA_PERSISTOR_KEY);
        if (!empty($persistedData)) {
            $order = $this->collection->getNewEmptyItem();
            $order->addData($persistedData);
            $this->loadedData[$order->getParentOrderId() ?: 0] = $this->prepareOrderData($order);
            $this->dataPersistor->clear(static::DATA_PERSISTOR_KEY);
        }

        $this->loadAdditionalData();

        return $this->loadedData;
    }

    /**
     * @param OrderInterface $order
     *
     * @return array
     */
    private function prepareOrderData(OrderInterface $order): array
    {
        if (!$order->getParentShipmentId()) {
            $this->setDefaultValues($order);
        }
        $orderData = $order->getData();
        $orderData['additional_services'] = (array)$order->getCourierService();
        if ($order->getInsuranceAmount()) {
            $orderData['additional_services'][] = AdditionalServiceExtensionEnum::INSURANCE->value;
        }
        if ($order->getCod()) {
            $orderData['additional_services'][] = AdditionalServiceExtensionEnum::COD->value;
            $orderData = \array_merge($orderData, $this->prepareCodData($order));
        }
        if ($order->getCoverAddress()) {
            $orderData['additional_services'][] = AdditionalServiceExtensionEnum::COVER_ADDRESS->value;
            $orderData['cover_address_id'] = $order->getCoverAddress()->getParentId();
        }

        return $orderData;
    }

    /**
     * @param OrderInterface $order
     *
     * @return array
     */
    private function prepareCodData(OrderInterface $order): array
    {
        $codData = [];
        foreach ($order->getCod()->getData() as $key => $value) {
            $codData[static::PREFIX_COD_FIELDS . $key] = $value;
        }

        return $codData;
    }

    private function preparePackData(OrderInterface $order): array
    {

    }

    /**
     * @param OrderInterface $order
     *
     * @return void
     */
    private function setDefaultValues(OrderInterface $order): void
    {
        $parentOrder = $order->getParentOrder();
        if ($this->paymentHelper->isOrderCod($parentOrder) && !$order->getCod()) {
            $order->setCod($this->defaultsProvider->createDefaultCod($parentOrder));
        }
        $defaultAdditionalServices = $this->configProvider->getDefaultAdditionalServices();
        if (\in_array(AdditionalServiceExtensionEnum::INSURANCE->value, $defaultAdditionalServices)
            || $this->shipmentHelper->isInsuranceRequired($order->getCarrierCode())
        ) {
            $order->setInsuranceAmount($this->defaultsProvider->getDefaultInsuranceAmount($parentOrder));
        }
        $order->setCourierService(\array_values(\array_filter(
            $defaultAdditionalServices,
            fn (string $service) => \in_array($service, AdditionalServiceEnum::cases())
        )));
    }

    /**
     * @return void
     */
    private function loadAdditionalData(): void
    {
        foreach ($this->loadedData as &$orderData) {
            $orderData[static::CONFIG_SERVICES_AVAILABILITY] =
                $this->serviceAvailabilityProvider->getServicesAvailability();
            $orderData[static::CONFIG_PACK_TEMPLATES] = [];
            $orderData[static::CONFIG_DEFAULT_PACK_TEMPLATE] = null;
            /** @var PackTemplateInterface $packTemplate */
            foreach ($this->packTemplateRepository->findAll()->getItems() as $packTemplate) {
                $orderData[static::CONFIG_PACK_TEMPLATES][$packTemplate->getTemplateId()] = $packTemplate->getData();
                if ($packTemplate->isDefault()) {
                    $orderData[static::CONFIG_DEFAULT_PACK_TEMPLATE] = $packTemplate->getTemplateId();
                }
            }
        }
    }
}
