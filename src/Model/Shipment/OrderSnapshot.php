<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Webservice\Enum\Order\CodStatusEnum;
use Polkurier\Webservice\Enum\Order\OrderStatusCodeEnum;
use Polkurier\Webservice\Response\Order;

/**
 * Copies what Polkurier reports about an order onto the local shipment record.
 */
class OrderSnapshot
{
    /**
     * Cash-on-delivery states after which nothing more happens; null is a shipment without COD.
     */
    private const array FINAL_COD_STATUSES = [
        null,
        CodStatusEnum::Paid->value,
        CodStatusEnum::Settled->value,
        CodStatusEnum::Compensation->value,
    ];

    public const int RETURN_SETTLED_AFTER_DAYS = 14;

    public function apply(Order $order, ShipmentInterface $shipment): void
    {
        $waybills = [];
        foreach ($order->getWaybills() as $waybill) {
            $waybills[] = $waybill->getNumber();
        }

        $shipment->setPolkurierNumber($order->getNumber() ?? $shipment->getPolkurierNumber())
            ->setWaybills(\array_values(\array_unique(\array_filter($waybills))))
            ->setStatusCode($order->getStatusCode())
            ->setStatusLabel($order->getStatus())
            ->setStatusDate($order->getStatusDate()->format('Y-m-d H:i:s'))
            ->setDeliveredAt($order->getDeliveredDate()?->format('Y-m-d H:i:s'))
            ->setTrackingUrl($order->getTrackingUri() ?: $shipment->getTrackingUrl())
            ->setPriceNet($order->getPriceNet())
            ->setPriceGross($order->getPriceGross())
            ->setCodAmount($order->getCod()?->getAmount() ?: null)
            ->setCodStatus($order->getCod()?->getStatus()->value);

        if ($order->getStatusCode() === OrderStatusCodeEnum::Canceled->value) {
            $shipment->setState(ShipmentInterface::STATE_CANCELLED);
        } elseif (\in_array($shipment->getState(), [ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING, ShipmentInterface::STATE_FAILED], true)) {
            $shipment->setState(ShipmentInterface::STATE_CREATED);
        }
        $shipment->setIsFinal($this->isFinal($shipment));
    }

    /**
     * Whether nothing about the order will change any more.
     */
    public function isFinal(ShipmentInterface $shipment, ?\DateTimeImmutable $now = null): bool
    {
        if ($shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            return true;
        }

        return match ($shipment->getStatusCode()) {
            OrderStatusCodeEnum::Delivered->value => \in_array($shipment->getCodStatus(), self::FINAL_COD_STATUSES, true),
            // A return has no status for "back with the sender": it stays "on its way back" for good.
            // Once that has not changed for a while, the parcel is taken to be back.
            OrderStatusCodeEnum::Return->value => $this->isOlderThan(
                $shipment->getStatusDate(),
                self::RETURN_SETTLED_AFTER_DAYS,
                $now ?? new \DateTimeImmutable(),
            ),
            default => false,
        };
    }

    private function isOlderThan(?string $date, int $days, \DateTimeImmutable $now): bool
    {
        if ($date === null) {
            return false;
        }
        try {
            return new \DateTimeImmutable($date) < $now->modify('-' . $days . ' days');
        } catch (\Exception) {
            return false;
        }
    }
}
