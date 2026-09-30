<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control\ShipmentView;

class CancelButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        if (!$this->isCreated() || !$this->authorization->isAllowed('Polkurier_Shipping::shipment_cancel')) {
            return [];
        }

        return $this->post(
            (string)\__('Cancel'),
            'cancel',
            30,
            (string)\__('Cancel Polkurier order %1?', (string)$this->shipment()?->getPolkurierNumber()),
            (string)\__('The carrier will not collect the parcel, and its Magento shipment is removed so the items can be shipped again. This cannot be undone.'),
        );
    }
}
