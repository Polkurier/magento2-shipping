<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Carrier;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Api\Filter;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Model\Carrier\CarrierLogo;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;
use Polkurier\Shipping\Controller\Adminhtml\Carrier\Save;
use Polkurier\Shipping\Model\Pricing\Fixed;
use Polkurier\Shipping\Model\Pricing\Tiers;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\Collection;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;

/**
 * A carrier's checkout settings, with its price_info spread into the form's price and tier rows.
 */
class DataProvider extends AbstractDataProvider
{
    private Collection $carriers;

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
        private readonly StoreManagerInterface $storeManager,
        private readonly CarrierLogo $carrierLogo,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->carriers = $this->collection;
    }

    /**
     * The form narrows by its own request parameter, not through the collection.
     */
    public function addFilter(Filter $filter): void
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        $id = (int)$this->request->getParam('id');
        $result = [];
        foreach ($this->getEditedConfig() as $config) {
            $info = $config->getPriceInfo();
            $result[$id] = [
                'id' => $id,
                CarrierConfigInterface::CARRIER_CODE => $config->getCarrierCode(),
                CarrierConfigInterface::CARRIER_NAME => $config->getCarrierName(),
                CarrierConfigInterface::CUSTOM_LABEL => $config->getCustomLabel(),
                CarrierConfigInterface::LOGO => $config->getLogo() !== null ? [[
                    'file' => $config->getLogo(),
                    'name' => \basename((string)$config->getLogo()),
                    'url' => $this->carrierLogo->getUrlFor($config),
                ]] : [],
                CarrierConfigInterface::STORE_LABELS => \array_map(
                    static fn(CarrierLabelInterface $label): array => [
                        CarrierLabelInterface::STORE_ID => (string)$label->getStoreId(),
                        CarrierLabelInterface::LABEL => $label->getLabel(),
                    ],
                    $config->getStoreLabels(),
                ),
                CarrierConfigInterface::ACTIVE => $config->isActive() ? '1' : '0',
                CarrierConfigInterface::PRICING_MODEL => $config->getPricingModel(),
                Fixed::PRICE => $info[Fixed::PRICE] ?? '',
                Tiers::TIERS => \array_values((array)($info[Tiers::TIERS] ?? [])),
            ];
        }
        // What was typed, when saving it failed.
        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (\is_array($persisted) && $id && (int)($persisted['id'] ?? 0) === $id) {
            $result[$id] = \array_merge($result[$id] ?? [], $persisted, ['id' => $id]);
        }
        $this->dataPersistor->clear(Save::PERSISTOR_KEY);

        return $result;
    }

    /**
     * Offers every store view for store view labels.
     *
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $options = [];
        foreach ($this->storeManager->getStores() as $store) {
            $options[] = ['value' => (string)$store->getId(), 'label' => (string)$store->getName()];
        }
        $meta['general']['children'][CarrierConfigInterface::STORE_LABELS]['children']['record']['children']
            [CarrierLabelInterface::STORE_ID]['arguments']['data']['config']['options'] = $options;

        return $meta;
    }

    /**
     * @return CarrierConfigInterface[] the carrier being edited, or none
     */
    private function getEditedConfig(): array
    {
        if (!$this->carriers->isLoaded()) {
            $this->carriers->addFieldToFilter(
                CarrierConfigInterface::CONFIG_ID,
                ['eq' => (int)$this->request->getParam('id')],
            );
        }

        return $this->carriers->getItems();
    }
}
