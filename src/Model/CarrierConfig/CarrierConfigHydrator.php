<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\CarrierConfig;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterfaceFactory;
use Polkurier\Shipping\Enum\CarrierConfig\PricingModelEnum;
use Polkurier\Shipping\Model\Carrier\CarrierLogoStorage;
use Polkurier\Shipping\Model\Carrier\Pricing\FixedPriceCalculator;
use Polkurier\Shipping\Model\Carrier\Pricing\TieredPriceCalculator;

/**
 * Copies what the merchant entered for a carrier onto its configuration, checked the same way
 * from the carrier's page and from the carriers grid.
 */
readonly class CarrierConfigHydrator
{
    public function __construct(
        private CarrierLabelInterfaceFactory $labelFactory,
        private StoreManagerInterface $storeManager,
        private CarrierLogoStorage $carrierLogoStorage,
    ) {}

    /**
     * Everything the carrier's page posts.
     *
     * @param array<string, mixed> $data
     *
     * @throws LocalizedException when the prices are missing or invalid
     */
    public function hydrate(CarrierConfigInterface $config, array $data): void
    {
        $model = PricingModelEnum::tryFrom((string)($data[CarrierConfigInterface::FIELD_PRICING_MODEL] ?? ''))
            ?? PricingModelEnum::Fixed;
        $config->setCustomLabel(\trim((string)($data[CarrierConfigInterface::FIELD_CUSTOM_LABEL] ?? '')) ?: null)
            ->setLogo($this->logo($data))
            ->setStoreLabels($this->storeLabels($data))
            ->setActive((bool)($data[CarrierConfigInterface::FIELD_ACTIVE] ?? false))
            ->setPricingModel($model->value)
            ->setPriceInfo($model === PricingModelEnum::Fixed
                ? $this->fixedPrice((string)($data[FixedPriceCalculator::FIELD_PRICE] ?? ''))
                : $this->tiers((array)($data[TieredPriceCalculator::FIELD_TIERS] ?? [])));
    }

    /**
     * The columns a grid row changes: label, visibility and a fixed price. The order is changed by
     * dragging the rows ({@see \Polkurier\Shipping\Model\CarrierConfig\CarrierSortOrderUpdater}).
     *
     * @param array<string, mixed> $row
     *
     * @throws LocalizedException
     */
    public function applyRow(CarrierConfigInterface $config, array $row): void
    {
        if (\array_key_exists(CarrierConfigInterface::FIELD_CUSTOM_LABEL, $row)) {
            $config->setCustomLabel(\trim((string)$row[CarrierConfigInterface::FIELD_CUSTOM_LABEL]) ?: null);
        }
        if (\array_key_exists(CarrierConfigInterface::FIELD_ACTIVE, $row)) {
            $config->setActive((bool)$row[CarrierConfigInterface::FIELD_ACTIVE]);
        }
        if (\array_key_exists(FixedPriceCalculator::FIELD_PRICE, $row)
            && (string)$row[FixedPriceCalculator::FIELD_PRICE] !== ''
        ) {
            // Only a fixed price is a single number; tiers are edited on the carrier's page.
            if ($config->getPricingModel() !== PricingModelEnum::Fixed->value) {
                throw new LocalizedException(__(
                    '%1 is priced in tiers: change its prices on the carrier\'s page.',
                    $config->getCarrierName(),
                ));
            }
            $config->setPriceInfo($this->fixedPrice((string)$row[FixedPriceCalculator::FIELD_PRICE]));
        }
    }

    /**
     * @return array<string, float>
     * @throws LocalizedException
     */
    private function fixedPrice(string $price): array
    {
        $price = self::number($price);
        if (!\is_numeric($price) || (float)$price < 0) {
            throw new LocalizedException(__('Enter the price.'));
        }

        return [FixedPriceCalculator::FIELD_PRICE => (float)$price];
    }

    /**
     * @param array<mixed> $rows the form's tier rows; deleted and unpriced ones are dropped
     *
     * @return array<string, array<int, array<string, float>>>
     * @throws LocalizedException
     */
    private function tiers(array $rows): array
    {
        $tiers = [];
        foreach ($rows as $row) {
            if (!\is_array($row)
                || !empty($row['delete'])
                || !\is_numeric(self::number((string)($row[TieredPriceCalculator::FIELD_PRICE] ?? '')))
            ) {
                continue;
            }
            $tier = [
                TieredPriceCalculator::FIELD_SUBTOTAL_FROM
                    => (float)self::number((string)($row[TieredPriceCalculator::FIELD_SUBTOTAL_FROM] ?? 0)),
                TieredPriceCalculator::FIELD_WEIGHT_FROM
                    => (float)self::number((string)($row[TieredPriceCalculator::FIELD_WEIGHT_FROM] ?? 0)),
                TieredPriceCalculator::FIELD_PRICE
                    => (float)self::number((string)$row[TieredPriceCalculator::FIELD_PRICE]),
            ];
            if (\min($tier) < 0) {
                throw new LocalizedException(__('A price tier can\'t have a negative price or threshold.'));
            }
            $tiers[] = $tier;
        }
        if ($tiers === []) {
            throw new LocalizedException(__('Add at least one price tier.'));
        }

        return [TieredPriceCalculator::FIELD_TIERS => $tiers];
    }

    /**
     * A decimal comma is taken as a point.
     */
    private static function number(string $value): string
    {
        return \str_replace(',', '.', \trim($value));
    }

    /**
     * The image uploader posts the file it holds, or nothing once it's removed.
     *
     * @param array<string, mixed> $data
     */
    private function logo(array $data): ?string
    {
        $file = (string)($data[CarrierConfigInterface::FIELD_LOGO][0]['file'] ?? '');

        return $file !== '' && $this->carrierLogoStorage->isOwnFile($file) ? $file : null;
    }

    /**
     * Store view labels from the form's rows; empty ones and store views that don't exist are
     * dropped, and a store view listed twice keeps its last label.
     *
     * @param array<string, mixed> $data
     *
     * @return CarrierLabelInterface[]
     */
    private function storeLabels(array $data): array
    {
        $labels = [];
        foreach ((array)($data[CarrierConfigInterface::FIELD_STORE_LABELS] ?? []) as $row) {
            $storeId = (int)($row[CarrierLabelInterface::FIELD_STORE_ID] ?? 0);
            $label = \trim((string)($row[CarrierLabelInterface::FIELD_LABEL] ?? ''));
            if ($storeId === 0 || $label === '' || !$this->isStoreView($storeId)) {
                continue;
            }
            $labels[$storeId] = $this->labelFactory->create()->setStoreId($storeId)->setLabel($label);
        }

        return \array_values($labels);
    }

    private function isStoreView(int $storeId): bool
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ((int)$store->getId() === $storeId) {
                return true;
            }
        }

        return false;
    }
}
