<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\OrderPoint;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\QuotePointInterface;
use Polkurier\Shipping\Model\OrderPoint;
use Polkurier\Shipping\Model\OrderPoint\OrderPointRepository;
use Polkurier\Shipping\Model\OrderPointFactory;
use Polkurier\Shipping\Model\ResourceModel\OrderPoint as OrderPointResource;
use Polkurier\Shipping\Model\ResourceModel\OrderPoint\Collection;
use Polkurier\Shipping\Model\ResourceModel\OrderPoint\CollectionFactory;

#[CoversClass(OrderPointRepository::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderPointRepositoryTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>> the stored rows, by order id
     */
    private array $rows = [];

    public function testTheCheckoutsPointIsCopiedToTheOrderWhole(): void
    {
        $quotePoint = $this->createStub(QuotePointInterface::class);
        $quotePoint->method('getMethodCode')->willReturn('INPOST_PACZKOMAT');
        $quotePoint->method('getPointId')->willReturn('KRA235M');
        $quotePoint->method('getPointLabel')->willReturn('31-870 Kraków, 2 Pułku Lotniczego 44');
        $quotePoint->method('getPointCod')->willReturn(true);
        $repository = $this->createRepository();

        $repository->copyFromQuote($quotePoint, 15);

        $point = $repository->getForOrder(15);
        self::assertSame(
            ['INPOST_PACZKOMAT', 'KRA235M', '31-870 Kraków, 2 Pułku Lotniczego 44', true],
            [$point?->getMethodCode(), $point?->getPointId(), $point?->getPointLabel(), $point?->getPointCod()],
        );
    }

    public function testAnOrdersPointIsReplacedAndDeletedNotAddedTo(): void
    {
        $repository = $this->createRepository();
        $repository->replaceForOrder(15, 'INPOST_PACZKOMAT', 'KRA235M', null, null);

        $repository->replaceForOrder(15, 'DHL_POINT', '4515215', 'Kraków', false);

        self::assertCount(1, $this->rows);
        $point = $repository->getForOrder(15);
        self::assertSame(['4515215', false], [$point?->getPointId(), $point?->getPointCod()]);
        $repository->deleteForOrder(15);
        self::assertNull($repository->getForOrder(15));
        self::assertNull($repository->getForOrder(16), 'An order without a point has none.');
    }

    public function testSeveralOrdersPointsAreReadByOrderLeavingOutEmptyOnes(): void
    {
        $objects = new ObjectManager($this);
        $points = \array_map(
            static fn(array $row): OrderPoint => $objects->getObject(OrderPoint::class)->setData($row),
            [['order_id' => 15, 'point_id' => 'KRA235M'], ['order_id' => 16, 'point_id' => '']],
        );
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getItems')->willReturn($points);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($collection);

        $found = $this->createRepository($collections)->getForOrders([15, 16, 15]);

        self::assertSame([15], \array_keys($found));
        self::assertSame([], $this->createRepository()->getForOrders([]));
    }

    private function createRepository(?CollectionFactory $collections = null): OrderPointRepository
    {
        $objects = new ObjectManager($this);
        $factory = $this->createStub(OrderPointFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn(): OrderPoint => $objects->getObject(OrderPoint::class),
        );
        $resource = $this->createStub(OrderPointResource::class);
        $resource->method('load')->willReturnCallback(function (OrderPoint $point, int $orderId) use ($resource) {
            $point->setData($this->rows[$orderId] ?? []);

            return $resource;
        });
        $resource->method('save')->willReturnCallback(function (OrderPoint $point) use ($resource) {
            $this->rows[$point->getOrderId()] = $point->getData();

            return $resource;
        });
        $resource->method('delete')->willReturnCallback(function (OrderPoint $point) use ($resource) {
            unset($this->rows[$point->getOrderId()]);

            return $resource;
        });

        return new OrderPointRepository(
            $factory,
            $resource,
            $collections ?? $this->createStub(CollectionFactory::class),
        );
    }
}
