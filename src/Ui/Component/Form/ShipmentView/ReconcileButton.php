<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * Looks for the Polkurier order of a shipment whose placing is uncertain.
 */
class ReconcileButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $state = $this->shipment()?->getState();

        return \in_array($state, [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING], true)
            && $this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
            ? $this->post((string)__('Reconcile'), 'reconcile', 20)
            : [];
    }
}
