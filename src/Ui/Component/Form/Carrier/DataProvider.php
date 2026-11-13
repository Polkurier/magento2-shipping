<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Carrier;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;
use Polkurier\Shipping\Enum\Adminhtml\FormMemoryEnum;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\CarrierLogoStorage;
use Polkurier\Shipping\Model\Carrier\Pricing\FixedPriceCalculator;
use Polkurier\Shipping\Model\Carrier\Pricing\TieredPriceCalculator;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\DefaultSenderPointManager;
use Polkurier\Shipping\Model\PickupPoint\PointFinder;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\Collection;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;
use Polkurier\Shipping\Ui\Component\Form\FormDataProvider;
use Polkurier\Webservice\Dto\Response\Carrier;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * A carrier's checkout settings, with its price_info spread into the form's price and tier rows,
 * and the account's default drop-off point for it.
 */
class DataProvider extends FormDataProvider
{
    public const string FIELD_DEFAULT_SENDER_POINT = 'default_sender_point';

    /**
     * The default drop-off point as the form showed it, so saving changes it only when it was
     * changed in the form.
     */
    public const string FIELD_DEFAULT_SENDER_POINT_BEFORE = 'default_sender_point_before';

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
        private readonly CarrierCatalog $carrierCatalog,
        private readonly CarrierFeatureResolver $carrierFeatureResolver,
        private readonly DefaultSenderPointManager $defaultSenderPointManager,
        private readonly PointFinder $pointFinder,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly ConfigProvider $configProvider,
        private readonly UrlInterface $url,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->carriers = $this->collection;
    }

    /**
     * Offers every store view for store view labels, and the default drop-off point for a carrier
     * that can have one ({@see DefaultSenderPointManager::getForCarrier()}): on the map, its own points
     * that take parcels for sending, starting at the default sender address's postcode.
     *
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        foreach ($this->getEditedConfig() as $config) {
            $code = $config->getCarrierCode();
            $carrier = $this->findCarrier($code);
            $point = $carrier !== null ? $this->defaultSenderPointManager->getForCarrier($carrier) : null;
            if ($point === null) {
                continue;
            }
            $found = $this->pointFinder->find($point, $code);
            $meta['general']['children'][self::FIELD_DEFAULT_SENDER_POINT]['arguments']['data']['config'] = [
                'visible' => true,
                'map' => [
                    'baseUrl' => $this->configProvider->getMapUrl() . '/',
                    'tokenUrl' => $this->url->getUrl('polkurier/shipment/mapToken'),
                ],
                'providers' => $this->carrierFeatureResolver->getPointProviders($carrier),
                'postcode' => (string)$this->addressRepository->getDefaultSenderAddress()?->getPostcode(),
                'pointDetails' => (object)($found ? [$point => $this->pointFinder->describe($found)] : []),
            ];
        }
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
            $point = $this->defaultSenderPointManager->get()[$config->getCarrierCode()] ?? '';
            $result[$id][self::FIELD_DEFAULT_SENDER_POINT] = $point;
            $result[$id][self::FIELD_DEFAULT_SENDER_POINT_BEFORE] = $point;
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
     * The carrier as Polkurier offers it, or null when it isn't offered or can't be read.
     */
    private function findCarrier(string $code): ?Carrier
    {
        try {
            return $this->carrierCatalog->getCarrier($code);
        } catch (PolkurierException) {
            return null;
        }
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
