<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control\ShipmentView;

/**
 * Creates the Magento shipment of a placed Polkurier order that has none.
 */
class ShipButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return $this->isCreated()
            && $this->shipment()?->getShipmentId() === null
            && $this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
            ? $this->post((string)\__('Create Magento shipment'), 'ship', 60)
            : [];
    }
}
