<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\CarrierConfig;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Carrier\AvailableCarrierFilter;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;

/**
 * The merchant's carrier settings, loaded once per request.
 */
class CarrierConfigProvider implements ResetAfterRequestInterface
{
    /**
     * @var array<string, CarrierConfigInterface>|null keyed by carrier code
     */
    private ?array $loaded = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly AvailableCarrierFilter $availableCarrierFilter,
    ) {}

    /**
     * Carriers the merchant switched on, the account still offers and the configuration keeps
     * available, in display order.
     *
     * @return array<string, CarrierConfigInterface> keyed by carrier code
     */
    public function getActive(): array
    {
        return $this->availableCarrierFilter->filter(\array_filter(
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
                ->setOrder(CarrierConfigInterface::FIELD_SORT_ORDER, 'ASC')
                ->setOrder(CarrierConfigInterface::FIELD_CARRIER_NAME, 'ASC');
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

    /**
     * The carrier's label in the store, as customers see it, or null without its settings.
     */
    public function getLabel(string $carrierCode, int $storeId): ?string
    {
        return $carrierCode !== '' ? $this->get($carrierCode)?->getLabelForStore($storeId) : null;
    }

    public function reset(): void
    {
        $this->loaded = null;
    }

    public function _resetState(): void
    {
        $this->reset();
    }
}
