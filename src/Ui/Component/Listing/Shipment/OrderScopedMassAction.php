<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Shipment;

use Polkurier\Shipping\Ui\Component\MassAction\AllowedAction;

/**
 * A mass action of the order page's Polkurier tab. Magento's grids post only the selection and the
 * filters, and the tab's order is a parameter of the grid's URL, not a filter, so "Select All"
 * would reach every order's shipments. Instead, the order goes with the action's URL, and the
 * action takes that order's shipments only
 * ({@see \Polkurier\Shipping\Controller\Adminhtml\Shipment\RequestedShipments}). So does each entry of an
 * action's submenu.
 */
class OrderScopedMassAction extends AllowedAction
{
    public function prepare(): void
    {
        parent::prepare();
        $orderId = (int)$this->getContext()->getRequestParam('order_id');
        if (!$orderId) {
            return;
        }
        $scoped = static fn(string $url): string => \rtrim($url, '/') . '/order_id/' . $orderId . '/';
        $config = (array)$this->getData('config');
        if (\is_string($config['url'] ?? null)) {
            $config['url'] = $scoped($config['url']);
        }
        $actions = $config['actions'] ?? null;
        $actions = $actions instanceof \JsonSerializable ? $actions->jsonSerialize() : $actions;
        if (\is_array($actions)) {
            foreach ($actions as $key => $action) {
                if (\is_string($action['url'] ?? null)) {
                    $actions[$key]['url'] = $scoped($action['url']);
                }
            }
            $config['actions'] = $actions;
        }
        $this->setData('config', $config);
    }
}
