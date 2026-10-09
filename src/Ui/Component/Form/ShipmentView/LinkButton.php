<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * Links a shipment whose outcome is unknown to the Polkurier order the merchant found for it, among
 * the orders this shop placed.
 */
class LinkButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $shipment = $this->shipment();
        // Only an order sent with the shipment's ids can be linked to it.
        if ($shipment === null || $shipment->getState() !== ShipmentInterface::STATE_UNKNOWN
            || $shipment->getExternalOrderId() === null
            || !$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
        ) {
            return [];
        }
        return [
            'label' => (string)__('Link to a Polkurier order'),
            'class' => 'secondary',
            'on_click' => $this->onClickScriptBuilder->prompt(
                $this->actionUrl('adopt'),
                (string)__('Link to a Polkurier order'),
                (string)__(
                    'The number of the Polkurier order this shop placed for this shipment, '
                    . 'as the Polkurier panel shows it.',
                ),
                'polkurier_number',
            ),
            'sort_order' => 25,
        ];
    }
}
