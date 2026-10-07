<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\OrderPoint;

use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\QuotePointInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Model\OrderPoint;
use Polkurier\Shipping\Model\OrderPointFactory;
use Polkurier\Shipping\Model\ResourceModel\OrderPoint as OrderPointResource;
use Polkurier\Shipping\Model\ResourceModel\OrderPoint\CollectionFactory as OrderPointCollectionFactory;

/**
 * Stores the pickup point an order goes to.
 */
readonly class OrderPointRepository implements OrderPointRepositoryInterface
{
    public function __construct(
        private OrderPointFactory $orderPointFactory,
        private OrderPointResource $orderPointResource,
        private OrderPointCollectionFactory $orderPointCollectionFactory,
    ) {}

    public function getForOrder(int $orderId): ?OrderPointInterface
    {
        $point = $this->orderPointFactory->create();
        $this->orderPointResource->load($point, $orderId);

        return $point->getPointId() !== '' ? $point : null;
    }

    public function getForOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }
        $points = [];
        $collection = $this->orderPointCollectionFactory->create()
            ->addFieldToFilter(OrderPointInterface::FIELD_ORDER_ID, ['in' => \array_values(\array_unique($orderIds))]);
        foreach ($collection->getItems() as $point) {
            if ($point->getPointId() !== '') {
                $points[$point->getOrderId()] = $point;
            }
        }

        return $points;
    }

    public function save(OrderPointInterface $point): void
    {
        /** @var OrderPoint $point */
        $this->orderPointResource->save($point);
    }

    public function replaceForOrder(
        int $orderId,
        string $methodCode,
        string $pointId,
        ?string $pointLabel,
        ?bool $pointCod,
    ): void {
        $point = $this->getForOrder($orderId);
        if ($point === null) {
            $point = $this->orderPointFactory->create()->setOrderId($orderId);
            $point->isObjectNew(true);
        }
        $point->setMethodCode($methodCode)->setPointId($pointId)->setPointLabel($pointLabel)->setPointCod($pointCod);
        $this->save($point);
    }

    public function deleteForOrder(int $orderId): void
    {
        $point = $this->getForOrder($orderId);
        if ($point instanceof OrderPoint) {
            $this->orderPointResource->delete($point);
        }
    }

    public function copyFromQuote(QuotePointInterface $quotePoint, int $orderId): OrderPointInterface
    {
        $point = $this->orderPointFactory->create()
            ->setOrderId($orderId)
            ->setMethodCode($quotePoint->getMethodCode())
            ->setPointId($quotePoint->getPointId())
            ->setPointLabel($quotePoint->getPointLabel())
            ->setPointCod($quotePoint->getPointCod());
        $point->isObjectNew(true);
        $this->save($point);

        return $point;
    }
}
