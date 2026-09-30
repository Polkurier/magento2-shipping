<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;

/**
 * The merchant's carrier settings, loaded once per request.
 */
class CarrierConfigs
{
    /**
     * @var array<string, CarrierConfigInterface>|null keyed by carrier code
     */
    private ?array $loaded = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly AvailableCarriers $availableCarriers,
    ) {}

    /**
     * Carriers the merchant switched on, the account still offers and the configuration keeps
     * available, in display order.
     *
     * @return array<string, CarrierConfigInterface> keyed by carrier code
     */
    public function getActive(): array
    {
        return $this->availableCarriers->filter(\array_filter(
            $this->getAll(),
            static fn(CarrierConfigInterface $config): bool => $config->isActive() && $config->isAvailable(),
        ));
    }

    /**
     * @return array<string, CarrierConfigInterface> keyed by carrier code
     */
    public function getAll(): array
    {
        if ($this->loaded === null) {
            $collection = $this->collectionFactory->create()
                ->setOrder(CarrierConfigInterface::SORT_ORDER, 'ASC')
                ->setOrder(CarrierConfigInterface::CARRIER_NAME, 'ASC');
            $this->loaded = [];
            foreach ($collection->getItems() as $config) {
                $this->loaded[$config->getCarrierCode()] = $config;
            }
        }

        return $this->loaded;
    }

    public function get(string $carrierCode): ?CarrierConfigInterface
    {
        return $this->getAll()[$carrierCode] ?? null;
    }

    public function reset(): void
    {
        $this->loaded = null;
    }
}
