<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Model\CarrierConfigFactory;
use Polkurier\Shipping\Model\Pricing\PricingModelEnum;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Keeps one settings row per carrier the account offers, on each website.
 *
 * New carriers arrive switched off. A carrier the account no longer offers keeps its row, with
 * the merchant's settings, and is marked unavailable, so it disappears from checkout and comes
 * back as it was if the account offers it again.
 */
class CarrierConfigSynchronizer
{
    public function __construct(
        private readonly CarrierCatalog $catalog,
        private readonly CarrierConfigs $carrierConfigs,
        private readonly CarrierConfigRepositoryInterface $repository,
        private readonly CarrierConfigFactory $configFactory,
        private readonly WebsiteScope $websiteScope,
    ) {}

    /**
     * @return int how many carriers the account offers
     * @throws PolkurierException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function synchronize(int $websiteId): int
    {
        $this->catalog->clean();
        $this->carrierConfigs->reset();
        $carriers = $this->catalog->getAccountCarriers($this->websiteScope->getStoreId($websiteId));
        $existing = $this->carrierConfigs->getAll($websiteId);

        foreach ($carriers as $code => $carrier) {
            $config = $existing[$code] ?? $this->configFactory->create()
                ->setWebsiteId($websiteId)
                ->setCarrierCode($code)
                ->setActive(false)
                ->setPricingModel(PricingModelEnum::Fixed->value)
                ->setPriceInfo([]);
            $config->setCarrierName($carrier->getName());
            $config->setAvailable(true);
            $this->repository->save($config);
        }
        foreach ($existing as $code => $config) {
            if (!isset($carriers[$code]) && $config->isAvailable()) {
                $this->repository->save($config->setAvailable(false));
            }
        }
        $this->carrierConfigs->reset();

        return \count($carriers);
    }
}
