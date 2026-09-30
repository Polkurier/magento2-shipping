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
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Keeps one settings row per carrier the account offers.
 *
 * New carriers arrive switched off and last in order. A carrier the account no longer offers keeps
 * its row, with the merchant's settings, and is marked unavailable, so it disappears from checkout
 * and comes back as it was if the account offers it again.
 */
class CarrierConfigSynchronizer
{
    public function __construct(
        private readonly CarrierCatalog $catalog,
        private readonly CarrierConfigs $carrierConfigs,
        private readonly CarrierConfigRepositoryInterface $repository,
        private readonly CarrierConfigFactory $configFactory,
    ) {}

    /**
     * @return int how many carriers the account offers
     * @throws PolkurierException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function synchronize(): int
    {
        $this->catalog->clean();
        $this->carrierConfigs->reset();
        $carriers = $this->catalog->getAccountCarriers();
        $existing = $this->carrierConfigs->getAll();
        $last = \array_reduce($existing, static fn(int $max, $config): int => \max($max, $config->getSortOrder()), 0);

        foreach ($carriers as $code => $carrier) {
            $config = $existing[$code] ?? $this->configFactory->create()
                ->setCarrierCode($code)
                // A new carrier goes to the end of the list, where the merchant can drag it from.
                ->setSortOrder($last += CarrierOrder::STEP)
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
