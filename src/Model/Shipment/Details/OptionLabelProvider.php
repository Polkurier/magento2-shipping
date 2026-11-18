<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Details;

use Polkurier\Shipping\Model\Admin\Formatter;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodStatus;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Config\Source\ShipmentState;
use Polkurier\Shipping\Model\Config\Source\ShipmentType;

/**
 * Names for the codes in a shipment's details, from the module's option lists. A code without a
 * name is shown as it is.
 */
readonly class OptionLabelProvider
{
    public function __construct(
        private ShipmentState $states,
        private ShipmentType $shipmentTypes,
        private PackType $packTypes,
        private CodType $codTypes,
        private CodReturnType $codReturnTypes,
        private CodStatus $codStatuses,
        private AdditionalService $services,
    ) {}

    public function getStateLabel(string $state): string
    {
        return $this->findLabel($this->states->toArray(), $state);
    }

    public function getShipmentTypeLabel(mixed $type): string
    {
        return $this->findLabel($this->shipmentTypes->toArray(), $type);
    }

    public function getPackTypeLabel(mixed $type): string
    {
        return $this->findLabel($this->packTypes->toArray(), $type);
    }

    public function getCodTypeLabel(mixed $type): string
    {
        return $this->findLabel($this->codTypes->toArray(), $type);
    }

    public function getCodReturnTypeLabel(mixed $type): string
    {
        return $this->findLabel($this->codReturnTypes->toArray(), $type);
    }

    public function getCodStatusLabel(mixed $status): string
    {
        return $this->findLabel($this->codStatuses->toArray(), $status);
    }

    public function getServiceLabel(string $service): string
    {
        return $this->findLabel($this->services::getLabels(), $service);
    }

    /**
     * @param array<string, string> $labels
     */
    private function findLabel(array $labels, mixed $value): string
    {
        if ($value === null || $value === '') {
            return Formatter::NONE;
        }

        return (string)($labels[(string)$value] ?? $value);
    }
}
