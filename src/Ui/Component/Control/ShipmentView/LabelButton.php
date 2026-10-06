<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control\ShipmentView;

/**
 * Prints the shipment's label.
 */
class LabelButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return $this->isCreated() ? $this->link((string)\__('Print label'), 'label', 90, 'primary') : [];
    }
}
