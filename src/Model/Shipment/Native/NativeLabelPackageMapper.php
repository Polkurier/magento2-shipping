<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Native;

use Magento\Framework\Exception\LocalizedException;
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
     * A package's name to save it as a pack template under, which the packaging window adds to the
     * package's params.
     */
    public const string KEEP_AS_PARAM = 'polkurier_keep_as';

    public function __construct(
        private PackFactory $packFactory,
        private ItemFactory $itemFactory,
        private CarrierHelper $carrierHelper,
    ) {}

    /**
     * @param array<int|string, mixed> $packages as Magento posts them
     * @param array<int, float> $qtys what the shipment carries, by order item id
     *
     * @return Pack[]
     * @throws LocalizedException when the packages hold what the shipment doesn't carry
     */
    public function toPacks(array $packages, array $qtys): array
    {
        $this->assertPackedOnce($packages, $qtys);
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
     * No item is shipped twice: the packages hold no more of an item than the shipment carries,
     * and nothing it doesn't. Magento's packaging window keeps to that; this checks what's posted.
     *
     * @param array<int|string, mixed> $packages
     * @param array<int, float> $qtys what the shipment carries, by order item id
     *
     * @throws LocalizedException
     */
    private function assertPackedOnce(array $packages, array $qtys): void
    {
        $packed = [];
        foreach ($packages as $package) {
            foreach (\is_array($package) ? (array)($package['items'] ?? []) : [] as $key => $item) {
                $orderItemId = (int)(\is_array($item) && isset($item['order_item_id']) ? $item['order_item_id'] : $key);
                $packed[$orderItemId] = ($packed[$orderItemId] ?? 0.0)
                    + (float)(\is_array($item) ? ($item['qty'] ?? 0) : 0);
            }
        }
        foreach ($packed as $orderItemId => $qty) {
            if ($qty > 0 && !isset($qtys[$orderItemId])) {
                throw new LocalizedException(__('A package holds an item the shipment doesn\'t carry.'));
            }
            if ($qty > ($qtys[$orderItemId] ?? 0.0) + 0.0001) {
                throw new LocalizedException(__('The packages hold more of an item than the shipment carries.'));
            }
        }
    }

    /**
     * The packaging window's weight, in pounds or kilograms, in kilograms.
     */
    private function kilograms(float $weight, string $units): float
    {
        $from = $units === Weight::POUND ? Weight::POUND : Weight::KILOGRAM;

        return \round((float)$this->carrierHelper->convertMeasureWeight($weight, $from, Weight::KILOGRAM), 3);
    }

    /**
     * The packaging window's size, in inches or centimetres, in whole centimetres.
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
