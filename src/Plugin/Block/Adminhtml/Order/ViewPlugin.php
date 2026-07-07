<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Block\Adminhtml\Order;

use Magento\Sales\Block\Adminhtml\Order\View;
use Polkurier\Shipping\Model\PolkurierCarrier;

readonly class ViewPlugin
{
    /**
     * @param View $subject
     * @param View $result
     *
     * @return View
     */
    public function afterSetLayout(View $subject, View $result): View
    {
        $order = $subject->getOrder();
        if ($order?->getShippingMethod(true)?->getCarrierCode() !== PolkurierCarrier::CODE) {
            return $result;
        }

        $subject->updateButton(
            'order_ship',
            'onclick',
            \sprintf(
                "setLocation('%s')",
                $subject->getUrl(
                    'polkurier/order_shipment/new',
                    ['order_id' => $order->getId()]
                )
            )
        );

        return $result;
    }
}
