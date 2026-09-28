<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;
use Polkurier\Shipping\Model\WebsiteScope;

/**
 * The merchant's carrier settings of a website, loaded once per request.
 */
class CarrierConfigs
{
    /**
     * @var array<int, array<string, CarrierConfigInterface>>
     */
    private array $byWebsite = [];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Config $config,
        private readonly WebsiteScope $websiteScope,
    ) {}

    /**
     * Carriers the merchant switched on, the account still offers and the configuration keeps
     * available, in display order.
     *
     * @return array<string, CarrierConfigInterface> keyed by carrier code
     */
    public function getActive(int $websiteId): array
    {
        $available = $this->config->getAvailableCarriers($this->websiteScope->getStoreId($websiteId));

        return \array_filter(
            $this->getAll($websiteId),
            static fn(CarrierConfigInterface $config): bool => $config->isActive() && $config->isAvailable()
                && ($available === [] || \in_array($config->getCarrierCode(), $available, true)),
        );
    }

    /**
     * @return array<string, CarrierConfigInterface> keyed by carrier code
     */
    public function getAll(int $websiteId): array
    {
        if (!isset($this->byWebsite[$websiteId])) {
            $collection = $this->collectionFactory->create()
                ->addFieldToFilter(CarrierConfigInterface::WEBSITE_ID, ['eq' => $websiteId])
                ->setOrder(CarrierConfigInterface::SORT_ORDER, 'ASC')
                ->setOrder(CarrierConfigInterface::CARRIER_NAME, 'ASC');
            $this->byWebsite[$websiteId] = [];
            foreach ($collection->getItems() as $config) {
                $this->byWebsite[$websiteId][$config->getCarrierCode()] = $config;
            }
        }

        return $this->byWebsite[$websiteId];
    }

    public function get(int $websiteId, string $carrierCode): ?CarrierConfigInterface
    {
        return $this->getAll($websiteId)[$carrierCode] ?? null;
    }

    public function reset(): void
    {
        $this->byWebsite = [];
    }
}
