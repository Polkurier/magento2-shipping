<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Block\Adminhtml\Order\Address;

use Magento\Framework\Data\Form;
use Magento\Framework\Registry;
use Magento\Sales\Block\Adminhtml\Order\Address\Form as AddressForm;
use Magento\Sales\Model\Order\Address;
use Polkurier\Shipping\Model\PolkurierCarrier;

readonly class FormPlugin
{
    /**
     * @param Registry $registry
     */
    public function __construct(
        /**
         * @var Registry
         */
        private Registry $registry,
    ) {}

    /**
     * @param AddressForm $subject
     * @param Form $form
     *
     * @return Form
     */
    public function afterGetForm(AddressForm $subject, Form $form): Form
    {
        $address = $this->getAddress();
        if ($address?->getAddressType() !== Address::TYPE_SHIPPING
            || $address?->getOrder()?->getShippingMethod(true)?->getCarrierCode() !== PolkurierCarrier::CODE
        ) {
            return $form;
        }

        $form->getElement('main')->addField(
            'point_id',
            'text',
            [
                'name'  => 'point_id',
                'label' => __('Pickup point ID'),
                'value' => $address->getData('point_id')
            ]
        );

        return $form;
    }

    /**
     * @return Address
     */
    private function getAddress()
    {
        return $this->registry->registry('order_address');
    }
}
