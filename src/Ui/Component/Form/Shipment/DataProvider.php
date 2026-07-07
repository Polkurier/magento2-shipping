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
use Polkurier\Shipping\Enum\AdditionalServiceExtensionEnum;
use Polkurier\Shipping\Helper\PaymentHelper;
use Polkurier\Shipping\Model\Provider\ConfigProvider;
use Polkurier\Shipping\Model\Provider\Shipment\DefaultCodProvider;
use Polkurier\Shipping\Model\Provider\Shipment\DefaultInsuranceProvider;
use Polkurier\Shipping\Model\ResourceModel\Order\CollectionFactory;
use Polkurier\Webservice\Enum\AdditionalServiceEnum;

class DataProvider extends AbstractDataProvider
{
    public const string DATA_PERSISTOR_KEY = 'polkurier_order';

    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @param ConfigProvider $configProvider
     * @param PaymentHelper $paymentHelper
     * @param DataPersistorInterface $dataPersistor
     * @param DefaultCodProvider $defaultCodProvider
     * @param DefaultInsuranceProvider $defaultInsuranceProvider
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
         * @var DataPersistorInterface
         */
        private readonly DataPersistorInterface $dataPersistor,

        /**
         * @var DefaultCodProvider
         */
        private readonly DefaultCodProvider $defaultCodProvider,

        /**
         * @var DefaultInsuranceProvider
         */
        private readonly DefaultInsuranceProvider $defaultInsuranceProvider,

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
            $order->setData($persistedData);
            $this->loadedData[$order->getParentOrderId() ?: 0] = $this->prepareOrderData($order);
            $this->dataPersistor->clear(static::DATA_PERSISTOR_KEY);
        }

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
            foreach ($order->getCod()->getData() as $key => $value) {
                $orderData['cod_' . $key] = $value;
            }
        }
        if ($order->getCoverAddress()) {
            $orderData['additional_services'][] = AdditionalServiceExtensionEnum::COVER_ADDRESS->value;
            $orderData['cover_address_id'] = $order->getCoverAddress()->getParentId();
        }

        return $orderData;
    }

    public function setDefaultValues(OrderInterface $order): void
    {
        $parentOrder = $order->getParentOrder();
        if ($this->paymentHelper->isOrderCod($parentOrder) && !$order->getCod()) {
            $order->setCod($this->defaultCodProvider->createDefaultCod($parentOrder));
        }
        $defaultAdditionalServices = $this->configProvider->getDefaultAdditionalServices();
        if (\in_array(AdditionalServiceExtensionEnum::INSURANCE->value, $defaultAdditionalServices)) {
            $order->setInsuranceAmount($this->defaultInsuranceProvider->getDefaultInsuranceAmount($parentOrder));
        }
        $order->setCourierService(\array_values(\array_filter(
            $defaultAdditionalServices,
            fn (string $service) => \in_array($service, AdditionalServiceEnum::cases())
        )));
    }
}
