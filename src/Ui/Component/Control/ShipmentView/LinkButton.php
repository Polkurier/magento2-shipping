<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control\ShipmentView;

use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * Links a shipment whose outcome is unknown to the Polkurier order the merchant found for it.
 */
class LinkButton extends AbstractButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        if ($this->shipment()?->getState() !== ShipmentInterface::STATE_UNKNOWN
            || !$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
        ) {
            return [];
        }
        $script = \sprintf(
            "require(['mage/dataPost', 'Magento_Ui/js/modal/prompt'], function (dataPost, prompt) { prompt({title: %s, content: %s, actions: {confirm: function (number) { if (number && number.trim()) { dataPost().postData({action: %s, data: {polkurier_number: number.trim()}}); } }}}); });",
            $this->json->serialize((string)\__('Link to a Polkurier order')),
            $this->json->serialize((string)\__('The number of the Polkurier order that is this shipment, as the Polkurier panel shows it.')),
            $this->json->serialize($this->actionUrl('adopt')),
        );

        return [
            'label' => (string)\__('Link to a Polkurier order'),
            'class' => 'secondary',
            'on_click' => $script . ' return false;',
            'sort_order' => 25,
        ];
    }
}
