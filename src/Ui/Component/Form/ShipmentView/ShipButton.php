<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Polkurier\Shipping\Controller\Adminhtml\Shipment\Ship as ShipmentShip;

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
            && $this->getShipment()?->getShipmentId() === null
            && $this->authorization->isAllowed(ShipmentShip::ADMIN_RESOURCE)
            ? $this->buildPostButton((string)__('Create Magento shipment'), ShipmentShip::ROUTE, 60)
            : [];
    }
}
