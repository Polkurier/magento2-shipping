<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

/**
 * Packs the items of a shipment into as few parcels as it can, of the pack templates for
 * the shipment's type, from the products' shipping dimensions and weights.
 *
 * When one template takes everything left, that's the last parcel: the smallest such template.
 * Otherwise the template that takes the most of what's left, by volume, makes the next parcel, and
 * the rest goes on. A unit that fits no template gets a parcel of its own size. Parcels whose
 * contents fit one template together are then merged, and every parcel takes the smallest template
 * its contents fit.
 *
 * Whether units fit is worked out in space, not by volume: a box keeps its empty space as boxes, a
 * unit goes into the empty box it fills best, turned the way that fits snuggest, and the rest of
 * that box is cut into up to three boxes beside it. Units never overlap and a gap too narrow for a
 * unit never counts as room. Cutting the space this way can miss arrangements a person would find,
 * so now and then a parcel is bigger, or there is one more, than needed; none holds more than fits.
 *
 * The packing margin set for the store is added to every side of every unit first, for products
 * a little bigger than their dimensions say. A product without a weight weighs nothing while
 * packing, and is reported; its parcel takes its template's weight. Otherwise a parcel weighs what
 * the parcel weight setting says: what's in it, or its template's weight. A template's weight is
 * the most its parcel may weigh. Going over it is only pointed out on the shipment screen, or, when
 * the store is set to keep parcels under it,
 * avoided wherever another parcel or a bigger template can take the rest; an item too heavy for
 * every template is still packed by size.
 */
readonly class AutoPacker
{
    public function __construct(
        private OrderAmounts $amounts,
        private ItemDimensions $dimensions,
        private MasterData $masterData,
        private PackFactory $packFactory,
        private ItemFactory $itemFactory,
        private Config $config,
    ) {}

    /**
     * With no parcels to go by: as the parcels of the website go (parcels, envelopes or pallets),
     * or as given, e.g. what the carrier takes. Every parcel is of that kind, so a shipment never
     * mixes them: an item no template of the kind takes goes in one of its own size of the kind.
     *
     * @param array<int, float> $qtys shipped by order item id
     * @param string|null $shipmentType the kind; the website's when none
     * @return array{packs: Draft\Pack[], unmeasured: string[], unweighed: string[], unweighed_own: string[], templates: bool}
     *     as pack() gives it
     */
    public function packByItems(Order $order, array $qtys, ?string $shipmentType = null): array
    {
        $kind = $shipmentType ?: $this->config->getDefaultShipmentType((int)$order->getStoreId())->value;

        return $this->pack($order, $qtys, $kind);
    }

    /**
     * @param array<int, float> $qtys shipped by order item id
     * @param string|null $shipmentType only templates of this shipment type, e.g. no pallets for boxes
     * @return array{packs: Draft\Pack[], unmeasured: string[], unweighed: string[], unweighed_own: string[], templates: bool}
     *     the parcels, or why there are none: the items without dimensions, or no pack template
     *     with a size; the items without a weight, which the parcels' weights leave out; and of
     *     those, the ones in a parcel of their own size, with no template's weight to take
     */
    public function pack(Order $order, array $qtys, ?string $shipmentType = null): array
    {
        $qtys = \array_filter($qtys, static fn(float $qty): bool => $qty > 0);
        $items = $this->amounts->getItems($order);
        $sizes = $this->dimensions->get($order, \array_keys($qtys));
        $unmeasured = [];
        foreach (\array_keys($qtys) as $id) {
            if (!isset($sizes[$id])) {
                $unmeasured[] = $items[$id]['name'] ?? (string)$id;
            }
        }
        $templates = $this->getTemplates($shipmentType);
        $unweighed = [];
        foreach (\array_keys($qtys) as $id) {
            if (($items[$id]['weight'] ?? 0.0) <= 0) {
                $unweighed[] = $items[$id]['name'] ?? (string)$id;
            }
        }
        if ($qtys === [] || $unmeasured !== [] || $templates === []) {
            return ['packs' => [], 'unmeasured' => $unmeasured, 'unweighed' => $unweighed, 'unweighed_own' => [], 'templates' => $templates !== []];
        }

        $margin = $this->config->getPackingMargin((int)$order->getStoreId());
        if ($this->config->getWeightLimit((int)$order->getStoreId()) === WeightLimitEnum::Warn) {
            $templates = \array_map(static fn(array $template): array => ['max_weight' => null] + $template, $templates);
        }
        $units = [];
        foreach ($qtys as $id => $qty) {
            $size = self::sorted(\array_map(static fn(float $side): float => $side + $margin, $sizes[$id]));
            // Whole units one by one; a fraction of a unit, if any, as one more.
            for ($left = $qty; $left > 0.0001; $left -= 1) {
                $units[] = ['id' => $id, 'qty' => \min(1.0, $left), 'size' => $size, 'weight' => $items[$id]['weight'] ?? 0.0];
            }
        }

        $parcels = $this->merge($templates, $this->fill($templates, self::biggestFirst($units)));
        $parcelWeight = $this->config->getParcelWeight((int)$order->getStoreId());
        $packs = [];
        $unweighedOwn = [];
        foreach ($parcels as $parcel) {
            if ($parcel['template'] !== null) {
                $parcel['template'] = $this->smallest($templates, $parcel['units']) ?? $parcel['template'];
            }
            foreach ($parcel['template'] === null ? $parcel['units'] : [] as $unit) {
                if ($unit['weight'] <= 0) {
                    $unweighedOwn[$unit['id']] = $items[$unit['id']]['name'] ?? (string)$unit['id'];
                }
            }
            $pack = $this->toPack($parcel, $parcelWeight);
            // One of its own size is of the kind packed, so the shipment stays of one kind: the
            // smallest pallet it stands on, or an envelope.
            if ($pack->getTemplateId() === null && $shipmentType === ShipmentTypeEnum::Palette->value) {
                $pack->setPackType(PackKinds::palletFor($pack->getLength(), $pack->getWidth()));
            } elseif ($pack->getTemplateId() === null && $shipmentType === ShipmentTypeEnum::Envelope->value) {
                $pack->setPackType(PackKinds::ENVELOPE);
            }
            $packs[] = $pack;
        }

        return ['packs' => $packs, 'unmeasured' => [], 'unweighed' => $unweighed, 'unweighed_own' => \array_values($unweighedOwn), 'templates' => true];
    }

    /**
     * Parcel by parcel: the smallest template that takes everything left, or else the one that
     * takes the most of it by volume.
     *
     * @param array<int, array<string, mixed>> $templates smallest first
     * @param array<int, array<string, mixed>> $units biggest first
     * @return array<int, array{template: array<string, mixed>|null, units: array<int, array<string, mixed>>}>
     */
    private function fill(array $templates, array $units): array
    {
        $parcels = [];
        while ($units !== []) {
            $template = $this->smallest($templates, $units);
            if ($template !== null) {
                $parcels[] = ['template' => $template, 'units' => $units];
                break;
            }
            $best = null;
            foreach ($templates as $candidate) {
                [$packed, $rest] = self::packMost([$candidate['size']], $units, $candidate['max_weight']);
                $volume = \array_sum(\array_map(static fn(array $unit): float => self::volume($unit['size']), $packed));
                // Strictly more, so of two that take as much the smaller one stays.
                if ($packed !== [] && ($best === null || $volume > $best['volume'] + 0.0001)) {
                    $best = ['template' => $candidate, 'volume' => $volume, 'packed' => $packed, 'rest' => $rest];
                }
            }
            if ($best === null) {
                // Nothing left fits any template: the biggest unit gets the smallest box it fits by
                // size, too heavy as it is, or else a parcel of its own size.
                $unit = \array_shift($units);
                $parcels[] = ['template' => $this->smallest($templates, [$unit], false), 'units' => [$unit]];
                continue;
            }
            $parcels[] = ['template' => $best['template'], 'units' => $best['packed']];
            $units = $best['rest'];
        }

        return $parcels;
    }

    /**
     * Puts two parcels together wherever one template takes both, until no two go together.
     *
     * @param array<int, array<string, mixed>> $templates smallest first
     * @param array<int, array{template: array<string, mixed>|null, units: array<int, array<string, mixed>>}> $parcels
     * @return array<int, array{template: array<string, mixed>|null, units: array<int, array<string, mixed>>}>
     */
    private function merge(array $templates, array $parcels): array
    {
        $parcels = \array_values($parcels);
        for ($first = 0; $first < \count($parcels); $first++) {
            for ($second = $first + 1; $second < \count($parcels); $second++) {
                if ($parcels[$first]['template'] === null || $parcels[$second]['template'] === null) {
                    continue;
                }
                $units = self::biggestFirst(\array_merge($parcels[$first]['units'], $parcels[$second]['units']));
                $template = $this->smallest($templates, $units);
                if ($template !== null) {
                    $parcels[$first] = ['template' => $template, 'units' => $units];
                    \array_splice($parcels, $second, 1);
                    // The bigger parcel may now take in one checked before.
                    $second = $first;
                }
            }
        }

        return $parcels;
    }

    /**
     * @param array<int, array<string, mixed>> $units
     * @return array<int, array<string, mixed>>
     */
    private static function biggestFirst(array $units): array
    {
        \usort($units, static fn(array $a, array $b): int => self::volume($b['size']) <=> self::volume($a['size']));

        return $units;
    }

    /**
     * Packs as many of the units as fit, biggest first, skipping those that don't, by size or by
     * the weight given.
     *
     * @param array<int, float[]> $spaces
     * @param array<int, array<string, mixed>> $units biggest first
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} packed and left
     */
    private static function packMost(array $spaces, array $units, ?float $maxWeight = null): array
    {
        $packed = [];
        $rest = [];
        $weight = 0.0;
        foreach ($units as $unit) {
            $unitWeight = $unit['weight'] * $unit['qty'];
            if ($maxWeight !== null && $weight + $unitWeight > $maxWeight + 0.0001) {
                $rest[] = $unit;
                continue;
            }
            $next = self::place($spaces, $unit['size']);
            if ($next === null) {
                $rest[] = $unit;
            } else {
                $spaces = $next;
                $packed[] = $unit;
                $weight += $unitWeight;
            }
        }

        return [$packed, $rest];
    }

    /**
     * @param array{template: array<string, mixed>|null, units: array<int, array<string, mixed>>} $parcel
     */
    private function toPack(array $parcel, ParcelWeightEnum $parcelWeight): Draft\Pack
    {
        $qtys = [];
        $weight = 0.0;
        $weighed = true;
        foreach ($parcel['units'] as $unit) {
            $qtys[$unit['id']] = ($qtys[$unit['id']] ?? 0.0) + $unit['qty'];
            $weight += $unit['weight'] * $unit['qty'];
            $weighed = $weighed && $unit['weight'] > 0;
        }
        $items = [];
        foreach ($qtys as $id => $qty) {
            $items[] = $this->itemFactory->create()->setOrderItemId((int)$id)->setQty(\round($qty, 4));
        }
        $weight = \round($weight, 3);
        $template = $parcel['template'];
        $pack = $this->packFactory->create()->setAmount(1)->setItems($items);
        if ($template === null) {
            // Fits no template: a parcel of the unit's own size.
            [$length, $width, $height] = $parcel['units'][0]['size'];

            return $pack->setTemplateId(null)
                ->setPackType(PackTypeEnum::Standard->value)
                ->setLength((int)\ceil($length))
                ->setWidth((int)\ceil($width))
                ->setHeight((int)\ceil($height))
                ->setWeight($weight);
        }

        return $pack->setTemplateId($template['id'])
            ->setPackType($template['pack_type'])
            ->setLength($template['length'])
            ->setWidth($template['width'])
            ->setHeight($template['height'])
            ->setWeight($parcelWeight->weigh((float)$template['weight'], $weight, $weighed, $template['tare'] ?? null));
    }

    /**
     * The pack templates with a size, of the shipment type if one is given, smallest
     * first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTemplates(?string $shipmentType): array
    {
        $templates = [];
        foreach ($this->masterData->getPackTemplates() as $template) {
            if (\min($template->getLength(), $template->getWidth(), $template->getHeight()) <= 0
                || ($shipmentType !== null && $shipmentType !== '' && $template->getShipmentType() !== '' && $template->getShipmentType() !== $shipmentType)
            ) {
                continue;
            }
            $templates[] = self::describe($template);
        }
        \usort($templates, static fn(array $a, array $b): int => self::volume($a['size']) <=> self::volume($b['size']));

        return $templates;
    }

    /**
     * @return array<string, mixed>
     */
    private static function describe(PackTemplateInterface $template): array
    {
        return [
            'id' => (int)$template->getId(),
            'pack_type' => $template->getPackType(),
            'length' => $template->getLength(),
            'width' => $template->getWidth(),
            'height' => $template->getHeight(),
            'weight' => $template->getWeight(),
            'tare' => $template->getTare(),
            // What it may hold: its maximum weight less the packaging's.
            'max_weight' => $template->getWeight() > 0 ? \max(0.0, $template->getWeight() - (float)$template->getTare()) : null,
            'size' => self::sorted([(float)$template->getLength(), (float)$template->getWidth(), (float)$template->getHeight()]),
        ];
    }

    /**
     * Packs the units, biggest first, into the empty boxes given.
     *
     * @param array<int, float[]> $spaces
     * @param array<int, array<string, mixed>> $units biggest first
     * @return array<int, float[]>|null the empty boxes after, or null when they don't all fit
     */
    private static function packInto(array $spaces, array $units): ?array
    {
        foreach ($units as $unit) {
            $spaces = self::place($spaces, $unit['size']);
            if ($spaces === null) {
                return null;
            }
        }

        return $spaces;
    }

    /**
     * The smallest template the units can all be packed into, and, unless told otherwise, that
     * holds what they weigh.
     *
     * @param array<int, array<string, mixed>> $templates smallest first
     * @param array<int, array<string, mixed>> $units biggest first
     * @return array<string, mixed>|null
     */
    private function smallest(array $templates, array $units, bool $byWeight = true): ?array
    {
        $weight = \array_sum(\array_map(static fn(array $unit): float => $unit['weight'] * $unit['qty'], $units));
        foreach ($templates as $template) {
            if ($byWeight && $template['max_weight'] !== null && $weight > $template['max_weight'] + 0.0001) {
                continue;
            }
            if (self::packInto([$template['size']], $units) !== null) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Puts a unit into the empty box it fills best, turned the way that fits snuggest, and cuts what's
     * left of that box into up to three boxes beside it: the longest leftover first, across the
     * whole box, so the biggest pieces stay whole.
     *
     * @param array<int, float[]> $spaces the empty boxes, each as its three sides
     * @param float[] $size the unit's sides
     * @return array<int, float[]>|null the empty boxes after, or null when it fits in none
     */
    private static function place(array $spaces, array $size): ?array
    {
        $best = null;
        foreach ($spaces as $index => $space) {
            foreach (self::turns($size) as $turn) {
                if ($turn[0] > $space[0] + 0.0001 || $turn[1] > $space[1] + 0.0001 || $turn[2] > $space[2] + 0.0001) {
                    continue;
                }
                // The fullest box first; within it, the way that leaves the unit snug against a
                // side, so the rest of the box stays in one usable piece.
                $waste = self::volume($space) - self::volume($turn);
                $gaps = \array_filter([$space[0] - $turn[0], $space[1] - $turn[1], $space[2] - $turn[2]], static fn(float $gap): bool => $gap > 0.0001);
                $score = [\round($waste, 4), $gaps === [] ? 0.0 : \min($gaps)];
                if ($best === null || $score < $best[2]) {
                    $best = [$index, $turn, $score];
                }
            }
        }
        if ($best === null) {
            return null;
        }
        [$index, $turn] = $best;
        $space = $spaces[$index];
        unset($spaces[$index]);

        $left = [$space[0] - $turn[0], $space[1] - $turn[1], $space[2] - $turn[2]];
        $axes = [0, 1, 2];
        \usort($axes, static fn(int $a, int $b): int => $left[$b] <=> $left[$a]);
        // Each cut takes the space beyond the unit along one side; the next ones stay within the
        // unit's extent along the sides already cut.
        $extent = $space;
        foreach ($axes as $axis) {
            $piece = $extent;
            $piece[$axis] = $left[$axis];
            if (\min($piece) > 0.0001) {
                $spaces[] = $piece;
            }
            $extent[$axis] = $turn[$axis];
        }

        return \array_values($spaces);
    }

    /**
     * The six ways a box can be turned.
     *
     * @param float[] $size
     * @return array<int, float[]>
     */
    private static function turns(array $size): array
    {
        [$a, $b, $c] = $size;

        return [[$a, $b, $c], [$a, $c, $b], [$b, $a, $c], [$b, $c, $a], [$c, $a, $b], [$c, $b, $a]];
    }

    /**
     * @param float[] $size
     * @return float[] longest first
     */
    private static function sorted(array $size): array
    {
        \rsort($size);

        return $size;
    }

    /**
     * @param float[] $size
     */
    private static function volume(array $size): float
    {
        return $size[0] * $size[1] * $size[2];
    }
}
