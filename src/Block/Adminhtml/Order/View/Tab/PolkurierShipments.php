<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Order\View\Tab;

use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Text\ListText;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * The order page's Polkurier tab. It lists every Polkurier shipment of the order, including those
 * that weren't placed and so have no Magento shipment in the Shipments tab. Holds the
 * polkurier_order_view_shipment_listing component. Orders of other carriers don't get the tab.
 */
class PolkurierShipments extends ListText implements TabInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly AuthorizationInterface $authorization,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getTabLabel(): string
    {
        return (string)__('Polkurier');
    }

    public function getTabTitle(): string
    {
        return (string)__('Polkurier shipments of the order');
    }

    public function canShowTab(): bool
    {
        $order = $this->registry->registry('current_order');

        return $order instanceof Order
            && Polkurier::isOrderOf($order)
            && $this->authorization->isAllowed('Polkurier_Shipping::shipments');
    }

    public function isHidden(): bool
    {
        return false;
    }
}
