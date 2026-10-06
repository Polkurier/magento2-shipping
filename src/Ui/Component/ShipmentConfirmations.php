<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component;

use Magento\Framework\Phrase;

/**
 * What the shipments grid and the shipment's page ask before cancelling or discarding a shipment.
 */
final class ShipmentConfirmations
{
    /**
     * @return array{title: Phrase, message: Phrase}
     */
    public static function cancel(string $polkurierNumber): array
    {
        return [
            'title' => \__('Cancel Polkurier order %1?', $polkurierNumber),
            'message' => \__('The carrier will not collect the parcel. Its Magento shipment stays, without the label and tracking numbers, for another Polkurier order. This cannot be undone.'),
        ];
    }

    /**
     * @return array{title: Phrase, message: Phrase}
     */
    public static function discard(): array
    {
        return [
            'title' => \__('Discard this shipment?'),
            'message' => \__('It wasn\'t placed, so nothing is cancelled in Polkurier. What was filled in for it is lost.'),
        ];
    }
}
