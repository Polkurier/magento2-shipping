<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Shipment;

use Polkurier\Shipping\Ui\Component\MassAction\AllowedAction;

/**
 * A mass action of the order page's Polkurier tab. Magento's grids post only the selection and the
 * filters, and the tab's order is a parameter of the grid's URL, not a filter, so "Select All"
 * would reach every order's shipments. Instead, the order goes with the action's URL, and the
 * action takes that order's shipments only
 * ({@see \Polkurier\Shipping\Controller\Adminhtml\Shipment\RequestedShipments}).
 */
class OrderScopedMassAction extends AllowedAction
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
