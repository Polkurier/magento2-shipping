<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Framework\AuthorizationInterface;
use Magento\Sales\Model\Order;
use Magento\Shipping\Block\Adminhtml\View;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

/**
 * Buttons on the page of a Magento shipment of a Polkurier order. "Polkurier order" opens the
 * details of the shipment's Polkurier order, when it has one.
 *
 * "Add a Polkurier order" shows on a Magento shipment without an active Polkurier order: one made
 * in Magento itself, or one whose Polkurier order was cancelled. The shipment screen then places a
 * Polkurier order for that shipment's items and links it to the shipment (Magento's own "Create
 * Shipping Label" does the same with the packaging window).
 */
readonly class ShipmentViewPlugin
{
    public const string BUTTON = 'polkurier_add_order';
    public const string VIEW_BUTTON = 'polkurier_view_order';

    public function __construct(
        private AuthorizationInterface $authorization,
        private NativeShipmentService $nativeShipmentService,
        private ShipmentFinder $shipmentFinder,
    ) {}

    /**
     * @param mixed $layout
     *
     * @return array{0: mixed}
     */
    public function beforeSetLayout(View $subject, $layout): array
    {
        $shipment = $subject->getShipment();
        $order = $shipment?->getOrder();
        if (!$order instanceof Order || !Polkurier::isOrderOf($order)) {
            return [$layout];
        }
        // If it has had several (one cancelled, another placed on it since), the latest.
        $linked = $this->shipmentFinder->latestLinkedTo((int)$shipment->getId());
        if ($linked !== null && $this->authorization->isAllowed('Polkurier_Shipping::shipments')) {
            $subject->addButton(self::VIEW_BUTTON, [
                'label' => __('Polkurier order'),
                'onclick' => \sprintf(
                    "setLocation('%s')",
                    $subject->getUrl('polkurier/shipment/view', ['id' => $linked->getId()]),
                ),
                'class' => 'polkurier-view-order',
            ]);
        }
        if (!$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
            || !isset($this->nativeShipmentService->getFreeShipments($order)[(int)$shipment->getId()])
        ) {
            return [$layout];
        }
        $subject->addButton(self::BUTTON, [
            'label' => __('Add a Polkurier order'),
            'onclick' => \sprintf("setLocation('%s')", $subject->getUrl('polkurier/shipment/create', [
                'order_id' => $order->getId(),
                'shipment_id' => $shipment->getId(),
            ])),
            'class' => 'ship',
        ]);

        return [$layout];
    }
}
