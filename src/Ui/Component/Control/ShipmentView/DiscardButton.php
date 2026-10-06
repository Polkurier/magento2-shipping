<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control\ShipmentView;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Ui\Component\ShipmentConfirmations;

/**
 * For a shipment that wasn't placed: discarded, rather than sent again.
 */
class DiscardButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        if ($this->shipment()?->getState() !== ShipmentInterface::STATE_FAILED
            || !$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
        ) {
            return [];
        }

        $confirm = ShipmentConfirmations::discard();

        return $this->post((string)\__('Discard'), 'discard', 25, (string)$confirm['title'], (string)$confirm['message']);
    }
}
