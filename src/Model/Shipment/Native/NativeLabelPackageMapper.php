<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Native;

use Magento\Framework\Measure\Length;
use Magento\Framework\Measure\Weight;
use Magento\Shipping\Helper\Carrier as CarrierHelper;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;

/**
 * The packages from Magento's packaging window, as the parcels of a Polkurier order: in
 * kilograms and centimetres, with what each holds.
 */
readonly class NativeLabelPackageMapper
{
    /**
     * The key the packaging window adds to a package's params: the name to save the package under
     * as a pack template.
     */
    public const string KEEP_AS_PARAM = 'polkurier_keep_as';

    public function __construct(
        private PackFactory $packFactory,
        private ItemFactory $itemFactory,
        private CarrierHelper $carrierHelper,
    ) {}

    /**
     * @param array<int|string, mixed> $packages as Magento posts them
     *
     * @return Pack[]
     */
    public function toPacks(array $packages): array
    {
        $packs = [];
        foreach ($packages as $package) {
            $params = \is_array($package) ? (array)($package['params'] ?? []) : [];
            $units = (string)($params['dimension_units'] ?? '');
            // What it holds, as the packaging window put it in.
            $items = [];
            foreach (\is_array($package) ? (array)($package['items'] ?? []) : [] as $key => $item) {
                $qty = (float)(\is_array($item) ? ($item['qty'] ?? 0) : 0);
                if ($qty > 0) {
                    $items[] = $this->itemFactory->create()
                        ->setOrderItemId(
                            (int)(\is_array($item) && isset($item['order_item_id']) ? $item['order_item_id'] : $key),
                        )
                        ->setQty($qty);
                }
            }
            $packs[] = $this->packFactory->create()
                ->setItems($items)
                ->setTemplateId(null)
                ->setPackType(ParcelTypeMapper::normalize((string)($params['container'] ?? '')))
                ->setLength($this->centimetres((float)($params['length'] ?? 0), $units))
                ->setWidth($this->centimetres((float)($params['width'] ?? 0), $units))
                ->setHeight($this->centimetres((float)($params['height'] ?? 0), $units))
                ->setWeight(\max(
                    0.1,
                    $this->kilograms((float)($params['weight'] ?? 0), (string)($params['weight_units'] ?? '')),
                ))
                ->setAmount(1)
                // Saved as a pack template once placed, as asked in the packaging window.
                ->setKeepAs(\trim((string)($params[self::KEEP_AS_PARAM] ?? '')));
        }

        return $packs;
    }

    /**
     * Converts the packaging window's weight, in pounds or kilograms, to kilograms.
     */
    private function kilograms(float $weight, string $units): float
    {
        $from = $units === Weight::POUND ? Weight::POUND : Weight::KILOGRAM;

        return \round((float)$this->carrierHelper->convertMeasureWeight($weight, $from, Weight::KILOGRAM), 3);
    }

    /**
     * Converts the packaging window's size, in inches or centimetres, to whole centimetres.
     */
    private function centimetres(float $length, string $units): int
    {
        $from = $units === Length::INCH ? Length::INCH : Length::CENTIMETER;

        return (int)\ceil(\round(
            (float)$this->carrierHelper->convertMeasureDimension($length, $from, Length::CENTIMETER),
            2,
        ));
    }
}
