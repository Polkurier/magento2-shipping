<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * Opens the Polkurier shipment screen from the page of an order placed with a Polkurier method,
 * with a "Ship with Polkurier" button. Orders of other carriers get nothing. Magento's Ship button
 * keeps leading to Magento's shipment page, where labels are created.
 *
 * The button shows while the order has items left to ship.
 */
readonly class OrderViewPlugin
{
    public function __construct(
        private AuthorizationInterface $authorization,
    ) {}

    /**
     * @param mixed $layout
     *
     * @return array{0: mixed}
     */
    public function beforeSetLayout(View $subject, $layout): array
    {
        $order = $subject->getOrder();
        // Orders of other carriers are left to their own integrations.
        if ($order === null
            || !Polkurier::isOrderOf($order)
            || !$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
        ) {
            return [$layout];
        }
        // Alongside Magento's Ship only. A Polkurier order for a Magento shipment made without one
        // is added from that shipment's page ({@see \Polkurier\Shipping\Plugin\Adminhtml\ShipmentViewPlugin}).
        if (!$order->canShip()) {
            return [$layout];
        }
        // Placed next to Magento's "Ship" by {@see \Polkurier\Shipping\Plugin\Adminhtml\ShipButtonPositionPlugin}.
        $subject->addButton(ShipButtonPositionPlugin::BUTTON, [
            'label' => __('Ship with Polkurier'),
            'onclick' => \sprintf(
                "setLocation('%s')",
                $subject->getUrl('polkurier/shipment/create', ['order_id' => $order->getId()]),
            ),
            'class' => 'ship',
        ]);

        return [$layout];
    }
}
