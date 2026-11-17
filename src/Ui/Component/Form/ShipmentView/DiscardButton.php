<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Ui\Component\ShipmentConfirmationProvider;

/**
 * Discards a shipment that wasn't placed, rather than sending it again.
 */
class DiscardButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        if ($this->getShipment()?->getState() !== ShipmentInterface::STATE_FAILED
            || !$this->authorization->isAllowed('Magento_Sales::ship')
        ) {
            return [];
        }

        $confirm = ShipmentConfirmationProvider::discard();

        return $this->buildPostButton(
            (string)__('Discard'),
            'discard',
            25,
            (string)$confirm['title'],
            (string)$confirm['message'],
        );
    }
}
