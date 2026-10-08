<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Shipping\Model\Shipment\Screen\ShipmentOptionsProvider;

/**
 * The address book's addresses that can be printed on the label as the sender (marked "Can be a
 * cover address"), with none first.
 */
readonly class CoverAddress extends AbstractOptions
{
    public function __construct(
        private ShipmentOptionsProvider $shipmentOptionsProvider,
    ) {}

    public function toArray(): array
    {
        $options = ['' => (string)__('None: the sender address')];
        foreach ($this->shipmentOptionsProvider->getSenderOptions(true) as $option) {
            $options[$option['value']] = $option['label'];
        }

        return $options;
    }
}
