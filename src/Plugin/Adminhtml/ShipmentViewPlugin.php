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
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

/**
 * On the page of a Magento shipment of a Polkurier order: "Polkurier order", to its details, when
 * the shipment has one.
 *
 * And "Add a Polkurier order" on a Magento shipment without one that stands: made in Magento
 * itself, or whose Polkurier order was cancelled. The shipment screen then places one for that
 * shipment's items, on it (Magento's own "Create Shipping Label" does the same with the packaging
 * window).
 */
readonly class ShipmentViewPlugin
{
    public const string BUTTON = 'polkurier_add_order';
    public const string VIEW_BUTTON = 'polkurier_view_order';

    public function __construct(
        private AuthorizationInterface $authorization,
        private Config $config,
        private MagentoShipment $magentoShipment,
        private ShipmentFinder $finder,
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
        $linked = $this->linkedTo((int)$shipment->getId());
        if ($linked !== null && $this->authorization->isAllowed('Polkurier_Shipping::shipments')) {
            $subject->addButton(self::VIEW_BUTTON, [
                'label' => \__('Polkurier order'),
                'onclick' => \sprintf("setLocation('%s')", $subject->getUrl('polkurier/shipment/view', ['id' => $linked->getId()])),
                'class' => 'polkurier-view-order',
            ]);
        }
        if (!$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
            || !$this->config->isShipmentScreenEnabled((int)$order->getStoreId())
            || !isset($this->magentoShipment->getFreeShipments($order)[(int)$shipment->getId()])
        ) {
            return [$layout];
        }
        $subject->addButton(self::BUTTON, [
            'label' => \__('Add a Polkurier order'),
            'onclick' => \sprintf("setLocation('%s')", $subject->getUrl('polkurier/shipment/create', [
                'order_id' => $order->getId(),
                'shipment_id' => $shipment->getId(),
            ])),
            'class' => 'ship',
        ]);

        return [$layout];
    }

    /**
     * The shipment's Polkurier order, the latest when it has had several (one cancelled, another
     * placed on it since).
     */
    private function linkedTo(int $magentoShipmentId): ?ShipmentInterface
    {
        return $this->finder->latestLinkedTo($magentoShipmentId);
    }
}
