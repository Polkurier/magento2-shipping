<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Details;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;

/**
 * The Magento order and shipment a Polkurier order belongs to, as links for its details page.
 */
readonly class Links
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private MagentoShipmentRepository $magentoShipmentRepository,
        private UrlInterface $url,
    ) {}

    /**
     * @return array{label: string, url: string}|null null when the order is gone
     */
    public function order(int $orderId): ?array
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            return null;
        }

        return [
            'label' => '#' . $order->getIncrementId(),
            'url' => $this->url->getUrl('sales/order/view', ['order_id' => $orderId]),
        ];
    }

    /**
     * @return array{label: string, url: string}|null null when there is none (yet)
     */
    public function shipment(?int $shipmentId): ?array
    {
        if ($shipmentId === null) {
            return null;
        }
        try {
            $shipment = $this->magentoShipmentRepository->get($shipmentId);
        } catch (NoSuchEntityException) {
            return null;
        }

        return [
            'label' => '#' . $shipment->getIncrementId(),
            'url' => $this->url->getUrl('sales/shipment/view', ['shipment_id' => $shipmentId]),
        ];
    }
}
