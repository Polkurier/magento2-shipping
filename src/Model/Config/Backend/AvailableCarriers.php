<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Backend;

use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Model\CarrierConfigFactory;
use Polkurier\Shipping\Model\Provider\Ext\CarrierProvider;
use Polkurier\Webservice\Exception\AuthorizationException;

class AvailableCarriers extends Value
{
    private readonly SerializerInterface $serializer;

    /**
     * @param CarrierConfigFactory $configFactory
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
         * @var CarrierConfigFactory
         */
        private readonly CarrierConfigFactory $configFactory,

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
        $this->serializer = SerializerBuilder::create()->enableEnumSupport()->build();
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
                    $config->setExtData($this->serializer->toArray($extCarriersData[$config->getCarrierCode()]));
                    $this->configRepository->save($config);
                }
            }

            // Create new entries
            $carriersToAdd = \array_flip($carriersToAdd);
            foreach ($carriersToAdd as $carrierCode) {
                $config = $this->configFactory->create();
                $config->setCarrierCode($carrierCode);
                $config->setExtData($this->serializer->toArray($extCarriersData[$carrierCode]));
                $this->configRepository->save($config);
            }
        } catch (AuthorizationException $e) {
        }
    }
}
