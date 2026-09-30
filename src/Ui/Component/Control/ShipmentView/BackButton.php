<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control\ShipmentView;

class BackButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => \__('Back'),
            'on_click' => \sprintf("setLocation('%s');", $this->url->getUrl('polkurier/shipment/index')),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
