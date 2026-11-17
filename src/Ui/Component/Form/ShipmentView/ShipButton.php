<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

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
            && $this->authorization->isAllowed('Magento_Sales::ship')
            ? $this->buildPostButton((string)__('Create Magento shipment'), 'ship', 60)
            : [];
    }
}
