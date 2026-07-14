<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider\Shipment;

use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;

readonly class AdditionalServiceAvailabilityProvider
{
    /**
     * @param CarrierConfigRepositoryInterface $carrierConfigRepository
     */
    public function __construct(
        /**
         * @var CarrierConfigRepositoryInterface
         */
        private CarrierConfigRepositoryInterface $carrierConfigRepository,
    ) {}

    /**
     * @return array<string, string[]>
     */
    public function getServicesAvailability(): array
    {
        $availability = [];
        foreach ($this->carrierConfigRepository->findActive() as $carrier) {
            $availability[$carrier->getCarrierCode()] = $carrier->getAdditionalServices();
        }

        return $availability;
    }
}
