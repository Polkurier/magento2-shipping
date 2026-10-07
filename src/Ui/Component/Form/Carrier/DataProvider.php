<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Carrier;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;
use Polkurier\Shipping\Enum\FormMemoryEnum;
use Polkurier\Shipping\Model\Carrier\CarrierLogoStorage;
use Polkurier\Shipping\Model\Carrier\Pricing\FixedPriceCalculator;
use Polkurier\Shipping\Model\Carrier\Pricing\TieredPriceCalculator;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\Collection;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;
use Polkurier\Shipping\Ui\Component\Form\FormDataProvider;

/**
 * A carrier's checkout settings, with its price_info spread into the form's price and tier rows.
 */
class DataProvider extends FormDataProvider
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
        private readonly CarrierLogoStorage $carrierLogoStorage,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->carriers = $this->collection;
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
        $meta['general']['children'][CarrierConfigInterface::FIELD_STORE_LABELS]['children']['record']['children']
            [CarrierLabelInterface::FIELD_STORE_ID]['arguments']['data']['config']['options'] = $options;

        return $meta;
    }

    protected function load(): array
    {
        $id = (int)$this->request->getParam('id');
        $result = [];
        foreach ($this->getEditedConfig() as $config) {
            $info = $config->getPriceInfo();
            $result[$id] = [
                'id' => $id,
                CarrierConfigInterface::FIELD_CARRIER_CODE => $config->getCarrierCode(),
                CarrierConfigInterface::FIELD_CARRIER_NAME => $config->getCarrierName(),
                CarrierConfigInterface::FIELD_CUSTOM_LABEL => $config->getCustomLabel(),
                CarrierConfigInterface::FIELD_LOGO => $config->getLogo() !== null ? [[
                    'file' => $config->getLogo(),
                    'name' => \basename((string)$config->getLogo()),
                    'url' => $this->carrierLogoStorage->getUrlFor($config),
                ]] : [],
                CarrierConfigInterface::FIELD_STORE_LABELS => \array_map(
                    static fn(CarrierLabelInterface $label): array => [
                        CarrierLabelInterface::FIELD_STORE_ID => (string)$label->getStoreId(),
                        CarrierLabelInterface::FIELD_LABEL => $label->getLabel(),
                    ],
                    $config->getStoreLabels(),
                ),
                CarrierConfigInterface::FIELD_ACTIVE => $config->isActive() ? '1' : '0',
                CarrierConfigInterface::FIELD_PRICING_MODEL => $config->getPricingModel(),
                FixedPriceCalculator::FIELD_PRICE => $info[FixedPriceCalculator::FIELD_PRICE] ?? '',
                TieredPriceCalculator::FIELD_TIERS => \array_values(
                    (array)($info[TieredPriceCalculator::FIELD_TIERS] ?? []),
                ),
            ];
        }
        // What was typed, when saving it failed.
        $persisted = $this->dataPersistor->get(FormMemoryEnum::Carrier->value);
        if (\is_array($persisted) && $id && (int)($persisted['id'] ?? 0) === $id) {
            $result[$id] = \array_merge($result[$id] ?? [], $persisted, ['id' => $id]);
        }
        $this->dataPersistor->clear(FormMemoryEnum::Carrier->value);

        return $result;
    }

    /**
     * @return CarrierConfigInterface[] the carrier being edited, or none
     */
    private function getEditedConfig(): array
    {
        if (!$this->carriers->isLoaded()) {
            $this->carriers->addFieldToFilter(
                CarrierConfigInterface::FIELD_CONFIG_ID,
                ['eq' => (int)$this->request->getParam('id')],
            );
        }

        return $this->carriers->getItems();
    }
}
