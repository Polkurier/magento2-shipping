<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\ViewModel\Adminhtml;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\PickupPoint\PointDescriptionBuilder;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The pickup point the customer chose, for the order page's shipping information. Only for an order
 * whose method delivers to pickup points alone, and whose shipping description doesn't name it
 * already (see {@see PointDescriptionBuilder}); the point is changed for a shipment on the shipment
 * screen, not here.
 */
readonly class OrderPickupPoint implements ArgumentInterface
{
    public function __construct(
        private OrderPointRepositoryInterface $orderPointRepository,
        private CarrierCatalog $carrierCatalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private PointDescriptionBuilder $pointDescriptionBuilder,
    ) {}

    public function getPoint(Order $order): ?OrderPointInterface
    {
        $methodCode = Polkurier::methodCodeOf((string)$order->getShippingMethod());
        $point = $methodCode === null ? null : $this->orderPointRepository->getForOrder((int)$order->getEntityId());
        if ($methodCode === null || $point === null || $point->getPointId() === ''
            || $this->pointDescriptionBuilder->names((string)$order->getShippingDescription(), $point->getPointId())
        ) {
            return null;
        }
        try {
            $carrier = $this->carrierCatalog->getCarrier($methodCode);
        } catch (PolkurierException) {
            // Without the carrier list, the point the order went with is still worth showing.
            return $point;
        }

        return $carrier !== null && $this->carrierFeatureResolver->isRecipientPointRequired($carrier) ? $point : null;
    }
}
