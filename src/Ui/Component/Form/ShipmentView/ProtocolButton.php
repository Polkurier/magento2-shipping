<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

/**
 * Prints the shipment's handover protocol.
 */
class ProtocolButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return $this->isCreated() ? $this->buildLinkButton((string)__('Print handover protocol'), 'protocol', 80) : [];
    }
}
