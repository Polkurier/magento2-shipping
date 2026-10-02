<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\MassAction;

/**
 * A mass action of the order page's Polkurier tab. Magento's grids post no more than the selection
 * and the filters, and the tab's order is a parameter of the grid's address, not a filter, so
 * "Select All" would reach every order's shipments: the order goes with the action's address,
 * and the action takes that order's shipments only (Model\Shipment\RequestedShipments).
 */
class OrderScopedAction extends AllowedAction
{
    public function prepare(): void
    {
        parent::prepare();
        $orderId = (int)$this->getContext()->getRequestParam('order_id');
        $config = (array)$this->getData('config');
        if ($orderId && \is_string($config['url'] ?? null)) {
            $config['url'] = \rtrim($config['url'], '/') . '/order_id/' . $orderId . '/';
            $this->setData('config', $config);
        }
    }
}
