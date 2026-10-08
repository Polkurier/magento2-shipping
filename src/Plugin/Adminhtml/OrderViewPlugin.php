<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;

/**
 * Opens the Polkurier shipment screen from the page of an order placed with a Polkurier method,
 * with a "Ship with Polkurier" button. Orders of other carriers get nothing.
 *
 * Magento's Ship button keeps leading to Magento's shipment page, where labels are created, unless
 * labels there are turned off. Then Ship itself opens the shipment screen, and no second button is
 * added.
 *
 * The button stays while the order has items left to ship, or has a Magento shipment without a
 * Polkurier order.
 */
readonly class OrderViewPlugin
{
    public function __construct(
        private AuthorizationInterface $authorization,
        private ConfigProvider $configProvider,
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
        $storeId = (int)$order->getStoreId();
        if (!$this->configProvider->isShipmentScreenEnabled($storeId)) {
            return [$layout];
        }
        $url = $subject->getUrl('polkurier/shipment/create', ['order_id' => $order->getId()]);

        if (!$this->configProvider->isNativeLabelsEnabled($storeId)) {
            $subject->updateButton('order_ship', 'onclick', \sprintf("setLocation('%s')", $url));
        } else {
            // Placed next to Magento's "Ship" by {@see \Polkurier\Shipping\Plugin\Adminhtml\ShipButtonPositionPlugin}.
            $subject->addButton(ShipButtonPositionPlugin::BUTTON, [
                'label' => __('Ship with Polkurier'),
                'onclick' => \sprintf("setLocation('%s')", $url),
                'class' => 'ship',
            ]);
        }

        return [$layout];
    }
}
