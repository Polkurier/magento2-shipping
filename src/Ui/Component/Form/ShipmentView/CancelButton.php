<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Polkurier\Shipping\Model\Shipment\CancellationRule;
use Polkurier\Shipping\Ui\Component\ShipmentConfirmationProvider;

/**
 * Cancels the shipment's Polkurier order, once confirmed.
 */
class CancelButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $shipment = $this->shipment();
        if ($shipment === null
            || CancellationRule::refusal($shipment) !== null
            || !$this->authorization->isAllowed('Polkurier_Shipping::shipment_cancel')
        ) {
            return [];
        }

        $confirm = ShipmentConfirmationProvider::cancel((string)$shipment->getPolkurierNumber());

        return $this->post((string)__('Cancel'), 'cancel', 30, (string)$confirm['title'], (string)$confirm['message']);
    }
}
