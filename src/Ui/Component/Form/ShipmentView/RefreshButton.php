<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

/**
 * Reads the shipment's status from Polkurier again.
 */
class RefreshButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return $this->isCreated() ? $this->post((string)__('Update status'), 'refresh', 70) : [];
    }
}
