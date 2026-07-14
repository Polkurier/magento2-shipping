<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Helper\CarrierMapper;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\Provider\Ext\CarrierProvider;
use Polkurier\Webservice\Exception\AuthorizationException;

class AvailableCarriers extends Value
{
    /**
     * @param CarrierMapper $carrierMapper
     * @param CarrierConfigRepositoryInterface $configRepository
     * @param CarrierProvider $carrierProvider
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        /**
         * @var CarrierMapper
         */
        private readonly CarrierMapper $carrierMapper,

        /**
         * @var CarrierConfigRepositoryInterface
         */
        private readonly CarrierConfigRepositoryInterface $configRepository,

        /**
         * @var CarrierProvider
         */
        private readonly CarrierProvider $carrierProvider,

        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return void
     */
    public function beforeSave()
    {
        /** @var string[] $value */
        $value = $this->getValue() ?: [];
        $carriersToAdd = \array_flip($value);
        try {
            $carrierConfigs = $this->configRepository->findAll();
            $extCarriersData = $this->carrierProvider->getAvailableCarriers(true);

            // Update existing entries and remove the redundant
            foreach ($carrierConfigs as $config) {
                unset($carriersToAdd[$config->getCarrierCode()]);
                if (!\in_array($config->getCarrierCode(), $value)) {
                    $this->configRepository->delete($config);
                } else {
                    $configData = $this->carrierMapper->mapExtCarrier(
                        $extCarriersData[$config->getCarrierCode()]
                    )->getData();
                    $configData = $this->unsetOwnData($configData);
                    $config->addData($configData);
                    $this->configRepository->save($config);
                }
            }

            // Create new entries
            $carriersToAdd = \array_flip($carriersToAdd);
            foreach ($carriersToAdd as $carrierCode) {
                $this->configRepository->save($this->carrierMapper->mapExtCarrier($extCarriersData[$carrierCode]));
            }
        } catch (AuthorizationException $e) {
        }
    }

    /**
     * @param array $carrierConfigData
     *
     * @return array
     */
    private function unsetOwnData(array $carrierConfigData): array
    {
        unset(
            $carrierConfigData['id'],
            $carrierConfigData[CarrierConfig::FIELD_CONFIG_ID],
            $carrierConfigData[CarrierConfig::FIELD_CUSTOM_LABEL],
            $carrierConfigData[CarrierConfig::FIELD_IMAGE],
            $carrierConfigData[CarrierConfig::FIELD_PRICING_MODEL],
            $carrierConfigData[CarrierConfig::FIELD_PRICE_INFO],
            $carrierConfigData[CarrierConfig::FIELD_SORT_ORDER],
            $carrierConfigData[CarrierConfig::FIELD_ACTIVE]
        );

        return $carrierConfigData;
    }
}
