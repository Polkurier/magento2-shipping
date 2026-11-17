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
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;

/**
 * Opens the Polkurier shipment screen from the page of an order placed with a Polkurier method,
 * with a "Ship with Polkurier" button. Orders of other carriers get nothing. Magento's Ship button
 * keeps leading to Magento's shipment page, where labels are created.
 *
 * The button shows while a Polkurier order can be placed: the order has items left to ship, or a
 * Magento shipment without a Polkurier order of its own, such as one whose Polkurier order was
 * cancelled. With nothing left to ship, the new Polkurier order goes on the latest such shipment.
 */
readonly class OrderViewPlugin
{
    public function __construct(
        private AuthorizationInterface $authorization,
        private NativeShipmentService $nativeShipmentService,
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
            || !$this->authorization->isAllowed('Magento_Sales::ship')
        ) {
            return [$layout];
        }
        // A particular Magento shipment gets its Polkurier order from that shipment's page too
        // ({@see \Polkurier\Shipping\Plugin\Adminhtml\ShipmentViewPlugin}).
        if (!$this->nativeShipmentService->canPlaceFor($order)) {
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
