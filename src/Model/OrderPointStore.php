<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Model\ResourceModel\OrderPoint as OrderPointResource;
use Polkurier\Shipping\Model\ResourceModel\QuotePoint as QuotePointResource;

/**
 * Loads and stores the pickup point of a quote and of an order.
 */
class OrderPointStore
{
    public function __construct(
        private readonly OrderPointFactory $orderPointFactory,
        private readonly OrderPointResource $orderPointResource,
        private readonly QuotePointFactory $quotePointFactory,
        private readonly QuotePointResource $quotePointResource,
    ) {}

    public function getForOrder(int $orderId): ?OrderPointInterface
    {
        $point = $this->orderPointFactory->create();
        $this->orderPointResource->load($point, $orderId);

        return $point->getPointId() !== '' ? $point : null;
    }

    public function saveForOrder(OrderPointInterface $point): void
    {
        /** @var OrderPoint $point */
        $this->orderPointResource->save($point);
    }

    /**
     * A new point for an order, not saved yet.
     */
    public function createForOrder(int $orderId): OrderPointInterface
    {
        $point = $this->orderPointFactory->create();
        $point->setOrderId($orderId);
        $point->isObjectNew(true);

        return $point;
    }

    public function deleteForOrder(int $orderId): void
    {
        $point = $this->getForOrder($orderId);
        if ($point instanceof OrderPoint) {
            $this->orderPointResource->delete($point);
        }
    }

    /**
     * @param int $quoteAddressId the shipping address in multi-address checkout, else 0
     */
    public function getForQuote(int $quoteId, int $quoteAddressId = 0): ?QuotePoint
    {
        $point = $this->quotePointFactory->create();
        $this->quotePointResource->loadForQuote($point, $quoteId, $quoteAddressId);

        return $point->getPointId() !== '' ? $point : null;
    }

    public function saveForQuote(QuotePoint $point): void
    {
        $this->quotePointResource->save($point);
    }

    public function deleteForQuote(int $quoteId, int $quoteAddressId = 0): void
    {
        $point = $this->getForQuote($quoteId, $quoteAddressId);
        if ($point !== null) {
            $this->quotePointResource->delete($point);
        }
    }

    /**
     * Copies the quote's point onto the order.
     */
    public function copyToOrder(QuotePoint $quotePoint, int $orderId): OrderPointInterface
    {
        $point = $this->orderPointFactory->create()
            ->setOrderId($orderId)
            ->setMethodCode($quotePoint->getMethodCode())
            ->setPointId($quotePoint->getPointId())
            ->setPointLabel($quotePoint->getPointLabel())
            ->setPointCod($quotePoint->isPointCod());
        $point->isObjectNew(true);
        $this->saveForOrder($point);

        return $point;
    }
}
