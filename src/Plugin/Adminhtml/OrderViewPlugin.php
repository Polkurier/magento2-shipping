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
use Polkurier\Shipping\Model\Config;

/**
 * Opens the Polkurier shipment screen from the order page, with a "Ship with Polkurier" button.
 *
 * Magento's Ship button keeps leading to Magento's shipment page, where labels are created, unless
 * labels there are turned off: then, for orders placed with a Polkurier method, Ship opens the
 * shipment screen instead of a second button.
 */
class OrderViewPlugin
{
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly Config $config,
    ) {}

    /**
     * @param mixed $layout
     * @return array{0: mixed}
     */
    public function beforeSetLayout(View $subject, $layout): array
    {
        $order = $subject->getOrder();
        if ($order === null || !$order->canShip() || !$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')) {
            return [$layout];
        }
        $storeId = (int)$order->getStoreId();
        if (!$this->config->isShipmentScreenEnabled($storeId)) {
            return [$layout];
        }
        $url = $subject->getUrl('polkurier/shipment/create', ['order_id' => $order->getId()]);

        if (Polkurier::methodCodeOf((string)$order->getShippingMethod()) !== null
            && !$this->config->isNativeLabelsEnabled($storeId)
        ) {
            $subject->updateButton('order_ship', 'onclick', \sprintf("setLocation('%s')", $url));
        } else {
            $subject->addButton('polkurier_ship', [
                'label' => \__('Ship with Polkurier'),
                'onclick' => \sprintf("setLocation('%s')", $url),
                'class' => 'ship',
            ], 0, 45);
        }

        return [$layout];
    }
}
