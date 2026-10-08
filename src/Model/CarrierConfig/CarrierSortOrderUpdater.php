<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\CarrierConfig;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;

/**
 * Sets the order carriers are offered in at checkout, as rows are dragged in the carriers grid.
 *
 * The grid may show only some carriers: a page, a filter, or the available carriers. The rows it
 * sends take the places they held among all carriers, in their new order, so the carriers it didn't
 * show stay where they were. Every carrier is then numbered 10, 20, 30… in that order.
 */
readonly class CarrierSortOrderUpdater
{
    public const int STEP = 10;

    public function __construct(
        private CarrierConfigProvider $carrierConfigProvider,
        private CarrierConfigRepositoryInterface $carrierConfigRepository,
    ) {}

    /**
     * @param int[] $configIds the rows the grid showed, in their new order
     *
     * @throws LocalizedException
     */
    public function arrange(array $configIds): void
    {
        $this->carrierConfigProvider->reset();
        $all = \array_values($this->carrierConfigProvider->getAll());
        $byId = [];
        foreach ($all as $config) {
            $byId[(int)$config->getId()] = $config;
        }
        $moved = [];
        foreach (\array_unique($configIds) as $id) {
            if (isset($byId[$id])) {
                $moved[] = $byId[$id];
            }
        }
        if ($moved === []) {
            throw new LocalizedException(__('None of these carriers exists any more. Reload the list.'));
        }

        $movedIds = \array_map(static fn(CarrierConfigInterface $config): int => (int)$config->getId(), $moved);
        $arranged = [];
        foreach ($all as $config) {
            // Each place a moved row held goes to the next row in its new order.
            $arranged[] = \in_array((int)$config->getId(), $movedIds, true) ? \array_shift($moved) : $config;
        }
        foreach ($arranged as $position => $config) {
            $sortOrder = ($position + 1) * self::STEP;
            if ($config->getSortOrder() !== $sortOrder) {
                $this->carrierConfigRepository->save($config->setSortOrder($sortOrder));
            }
        }
        $this->carrierConfigProvider->reset();
    }
}
