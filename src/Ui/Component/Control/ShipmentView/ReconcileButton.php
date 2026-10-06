<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control\ShipmentView;

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
        return \in_array($this->shipment()?->getState(), [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING], true)
            && $this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
            ? $this->post((string)\__('Reconcile'), 'reconcile', 20)
            : [];
    }
}
