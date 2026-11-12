<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PickupPoint;

use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\CourierPoint;

/**
 * Looks points up by code on Polkurier, for showing them by name and address next to their codes.
 */
readonly class PointFinder
{
    public function __construct(
        private CarrierCatalog $carrierCatalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
    ) {}

    /**
     * A point among the map providers of the carrier, or of all the carriers when none is given:
     * false when Polkurier has no such point, null when that can't be told.
     */
    public function find(string $pointId, ?string $carrierCode = null): CourierPoint|false|null
    {
        if ($carrierCode === '' || $pointId === '') {
            return null;
        }
        try {
            $providers = [];
            $carriers = $carrierCode === null
                ? $this->carrierCatalog->getCarriers()
                : [$this->carrierCatalog->getCarrier($carrierCode)];
            foreach ($carriers as $carrier) {
                if ($carrier !== null) {
                    $providers = [...$providers, ...$this->carrierFeatureResolver->getPointProviders($carrier)];
                }
            }
            if ($providers === []) {
                return null;
            }
            $point = $this->carrierCatalog->findPoint($pointId, \array_values(\array_unique($providers)));
        } catch (PolkurierException) {
            return null;
        }

        return $point ?? false;
    }

    /**
     * What's shown next to the point's code: its name, or its description when it has none (e.g.
     * "DPD Pickup – Sklep Żabka"), and its address.
     *
     * @return array{name: string, label: string}
     */
    public function describe(CourierPoint $point): array
    {
        return [
            'name' => $point->getName() !== '' ? $point->getName() : $point->getDescription(),
            'label' => (string)$point->getAddress(),
        ];
    }
}
