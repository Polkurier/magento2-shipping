<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\ViewModel\Adminhtml;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Checkout\PointDescription;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The pickup point the customer chose, for the order page's shipping information. Only for an
 * order whose method delivers to pickup points alone, and whose shipping description doesn't name
 * it already (see PointDescription); the point is changed for a shipment on the shipment screen,
 * not here.
 */
readonly class OrderPickupPoint implements ArgumentInterface
{
    public function __construct(
        private OrderPointStore $pointStore,
        private CarrierCatalog $catalog,
        private CarrierFeatures $features,
        private PointDescription $pointDescription,
    ) {}

    public function getPoint(Order $order): ?OrderPointInterface
    {
        $methodCode = Polkurier::methodCodeOf((string)$order->getShippingMethod());
        $point = $methodCode === null ? null : $this->pointStore->getForOrder((int)$order->getEntityId());
        if ($methodCode === null || $point === null || $point->getPointId() === ''
            || $this->pointDescription->names((string)$order->getShippingDescription(), $point->getPointId())
        ) {
            return null;
        }
        try {
            $carrier = $this->catalog->getCarrier($methodCode);
        } catch (PolkurierException) {
            // Without the carrier list, the point the order went with is still worth showing.
            return $point;
        }

        return $carrier !== null && $this->features->isRecipientPointRequired($carrier) ? $point : null;
    }
}
