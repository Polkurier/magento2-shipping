<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterfaceFactory;
use Polkurier\Shipping\Enum\PricingModelEnum;
use Polkurier\Shipping\Model\Pricing\Fixed;
use Polkurier\Shipping\Model\Pricing\Tiers;

/**
 * Copies what the merchant entered for a carrier onto its configuration, checked the same way
 * from the carrier's page and from the carriers grid.
 */
readonly class CarrierConfigHydrator
{
    public function __construct(
        private CarrierLabelInterfaceFactory $labelFactory,
        private StoreManagerInterface $storeManager,
        private CarrierLogo $carrierLogo,
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
        $model = PricingModelEnum::tryFrom((string)($data['pricing_model'] ?? '')) ?? PricingModelEnum::Fixed;
        $config->setCustomLabel(\trim((string)($data['custom_label'] ?? '')) ?: null)
            ->setLogo($this->logo($data))
            ->setStoreLabels($this->storeLabels($data))
            ->setActive((bool)($data['active'] ?? false))
            ->setPricingModel($model->value)
            ->setPriceInfo($model === PricingModelEnum::Fixed
                ? $this->fixedPrice((string)($data[Fixed::PRICE] ?? ''))
                : $this->tiers((array)($data[Tiers::TIERS] ?? [])));
    }

    /**
     * The columns a grid row changes: label, visibility and a fixed price. The order is changed by
     * dragging the rows (CarrierOrder).
     *
     * @param array<string, mixed> $row
     *
     * @throws LocalizedException
     */
    public function applyRow(CarrierConfigInterface $config, array $row): void
    {
        if (\array_key_exists('custom_label', $row)) {
            $config->setCustomLabel(\trim((string)$row['custom_label']) ?: null);
        }
        if (\array_key_exists('active', $row)) {
            $config->setActive((bool)$row['active']);
        }
        if (\array_key_exists(Fixed::PRICE, $row) && (string)$row[Fixed::PRICE] !== '') {
            // Only a fixed price is a single number; tiers are edited on the carrier's page.
            if ($config->getPricingModel() !== PricingModelEnum::Fixed->value) {
                throw new LocalizedException(\__('%1 is priced in tiers: change its prices on the carrier\'s page.', $config->getCarrierName()));
            }
            $config->setPriceInfo($this->fixedPrice((string)$row[Fixed::PRICE]));
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
            throw new LocalizedException(\__('Enter the price.'));
        }

        return [Fixed::PRICE => (float)$price];
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
            if (!\is_array($row) || !empty($row['delete']) || !\is_numeric(self::number((string)($row[Tiers::PRICE] ?? '')))) {
                continue;
            }
            $tier = [
                Tiers::SUBTOTAL_FROM => (float)self::number((string)($row[Tiers::SUBTOTAL_FROM] ?? 0)),
                Tiers::WEIGHT_FROM => (float)self::number((string)($row[Tiers::WEIGHT_FROM] ?? 0)),
                Tiers::PRICE => (float)self::number((string)$row[Tiers::PRICE]),
            ];
            if (\min($tier) < 0) {
                throw new LocalizedException(\__('A price tier can\'t have a negative price or threshold.'));
            }
            $tiers[] = $tier;
        }
        if ($tiers === []) {
            throw new LocalizedException(\__('Add at least one price tier.'));
        }

        return [Tiers::TIERS => $tiers];
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
        $file = (string)($data['logo'][0]['file'] ?? '');

        return $file !== '' && $this->carrierLogo->isOwnFile($file) ? $file : null;
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
        foreach ((array)($data[CarrierConfigInterface::STORE_LABELS] ?? []) as $row) {
            $storeId = (int)($row[CarrierLabelInterface::STORE_ID] ?? 0);
            $label = \trim((string)($row[CarrierLabelInterface::LABEL] ?? ''));
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
