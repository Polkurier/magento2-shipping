<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Phrase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Webservice\Enum\Order\OrderStatusCodeEnum;

/**
 * Whether a shipment's Polkurier order may be cancelled: as the Polkurier panel decides it, as far
 * as the stored copy of the order tells. The panel also weighs things the API doesn't report (an
 * order under the account's own carrier agreement, and the account's 48-hour hold), so an order
 * the panel would cancel may be refused here, never the other way round; Polkurier still has the
 * last word when the order is cancelled.
 */
class CancellationRule
{
    /**
     * Freight, which the panel cancels only before Polkurier confirms it.
     */
    public const string FREIGHT_CARRIER = 'SPEDYCJA';

    /**
     * Statuses of a parcel the carrier has.
     */
    private const array WITH_THE_CARRIER = [
        OrderStatusCodeEnum::InTransit->value,
        OrderStatusCodeEnum::Delivered->value,
        OrderStatusCodeEnum::Return->value,
        OrderStatusCodeEnum::Exception->value,
    ];

    /**
     * Why the order can't be cancelled, or null when it can.
     */
    public static function getRefusal(ShipmentInterface $shipment): ?Phrase
    {
        $number = (string)$shipment->getPolkurierNumber();
        if ($number === '' || $shipment->getState() !== ShipmentInterface::STATE_CREATED) {
            return __('Only a placed Polkurier order can be cancelled.');
        }
        $status = $shipment->getStatusCode();
        if (!empty($shipment->getDetails()['has_invoice'])) {
            return __('Polkurier order %1 is invoiced, so it can\'t be cancelled.', $number);
        }
        if (\in_array($status, self::WITH_THE_CARRIER, true)) {
            return __('Polkurier order %1 is with the carrier, so it can\'t be cancelled.', $number);
        }
        // Polkurier reports a cancelled order and a cancelled waybill alike.
        if ($status === OrderStatusCodeEnum::Canceled->value) {
            return __('Polkurier reports order %1 as cancelled.', $number);
        }
        if ($shipment->getCarrierCode() === self::FREIGHT_CARRIER
            && ($shipment->getWaybills() !== [] || $status === OrderStatusCodeEnum::Confirmed->value)
        ) {
            return __('Polkurier has confirmed freight order %1, so it can\'t be cancelled.', $number);
        }

        return null;
    }
}
