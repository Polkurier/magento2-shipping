<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Details;

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
    private const string NONE = '—';

    public function __construct(
        private ShipmentState $states,
        private ShipmentType $shipmentTypes,
        private PackType $packTypes,
        private CodType $codTypes,
        private CodReturnType $codReturnTypes,
        private CodStatus $codStatuses,
        private AdditionalService $services,
    ) {}

    public function state(string $state): string
    {
        return $this->label($this->states->toArray(), $state);
    }

    public function shipmentType(mixed $type): string
    {
        return $this->label($this->shipmentTypes->toArray(), $type);
    }

    public function packType(mixed $type): string
    {
        return $this->label($this->packTypes->toArray(), $type);
    }

    public function codType(mixed $type): string
    {
        return $this->label($this->codTypes->toArray(), $type);
    }

    public function codReturnType(mixed $type): string
    {
        return $this->label($this->codReturnTypes->toArray(), $type);
    }

    public function codStatus(mixed $status): string
    {
        return $this->label($this->codStatuses->toArray(), $status);
    }

    public function service(string $service): string
    {
        return $this->label($this->services::labels(), $service);
    }

    /**
     * @param array<string, string> $labels
     */
    private function label(array $labels, mixed $value): string
    {
        if ($value === null || $value === '') {
            return self::NONE;
        }

        return (string)($labels[(string)$value] ?? $value);
    }
}
