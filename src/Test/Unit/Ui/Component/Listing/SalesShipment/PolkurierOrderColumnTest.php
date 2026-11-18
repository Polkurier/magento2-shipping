<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Listing\SalesShipment;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Config\Source\ShipmentState;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Test\Unit\Support\StandaloneEscaper;
use Polkurier\Shipping\Ui\Component\Listing\SalesShipment\PolkurierOrderColumn;

#[CoversClass(PolkurierOrderColumn::class)]
#[AllowMockObjectsWithoutExpectations]
class PolkurierOrderColumnTest extends TestCase
{
    public function testEachMagentoShipmentLinksItsPlacedPolkurierOrder(): void
    {
        $cells = $this->renderCells([
            $this->createShipment(1, 11, ShipmentInterface::STATE_CANCELLED, '24680-20'),
            $this->createShipment(2, 11, ShipmentInterface::STATE_CREATED, '24680-21'),
            $this->createShipment(3, 12, ShipmentInterface::STATE_CREATED, '24680-22'),
        ], [11, 12, 13]);

        self::assertSame([
            '<a href="polkurier/shipment/view/2">24680-21</a>',
            '<a href="polkurier/shipment/view/3">24680-22</a>',
            '',
        ], $cells, 'The cancelled one before it gives way; a shipment without one stays empty.');
    }

    public function testWithoutAPlacedOneTheLatestShowsWithItsState(): void
    {
        $cells = $this->renderCells([
            $this->createShipment(1, 11, ShipmentInterface::STATE_CANCELLED, '24680-20'),
            $this->createShipment(2, 11, ShipmentInterface::STATE_UNKNOWN, null),
        ], [11]);

        self::assertSame(['<a href="polkurier/shipment/view/2">#2 (Outcome unknown)</a>'], $cells);
    }

    public function testThePlacedOneStaysAgainstALaterOne(): void
    {
        $cells = $this->renderCells([
            $this->createShipment(2, 11, ShipmentInterface::STATE_CREATED, '24680-21'),
            $this->createShipment(4, 11, ShipmentInterface::STATE_FAILED, null),
        ], [11]);

        self::assertSame(['<a href="polkurier/shipment/view/2">24680-21</a>'], $cells);
    }


    public function testTheColumnShowsOnlyForAPolkurierOrdersShipments(): void
    {
        self::assertFalse($this->isDisabled('polkurier_DPD'));
        self::assertTrue($this->isDisabled('flatrate_flatrate'));
        self::assertTrue($this->isDisabled(null), 'An order that is gone.');
        self::assertFalse($this->isDisabled(false), 'The list of every shipment.');
    }

    /**
     * @param string|false|null $method the order's shipping method; null: no such order; false: no order in the request
     */
    private function isDisabled(string|false|null $method): bool
    {
        $column = $this->createColumn([], $method === false ? 0 : 29, $method === false ? null : $method);
        $column->prepare();

        return (bool)($column->getData('config')['componentDisabled'] ?? false);
    }

    /**
     * @param Shipment[] $shipments
     * @param int[] $magentoShipmentIds the grid's rows
     *
     * @return array<int, string|null>
     */
    private function renderCells(array $shipments, array $magentoShipmentIds): array
    {
        $source = $this->createColumn($shipments)->prepareDataSource(['data' => ['items' => \array_map(
            static fn(int $id): array => ['entity_id' => (string)$id],
            $magentoShipmentIds,
        )]]);

        return \array_map(
            static fn(array $item): ?string => $item['polkurier_order'] ?? null,
            $source['data']['items'],
        );
    }

    /**
     * @param Shipment[] $shipments
     */
    private function createColumn(array $shipments, int $orderId = 0, ?string $method = null): PolkurierOrderColumn
    {
        $context = $this->createStub(ContextInterface::class);
        $context->method('getProcessor')->willReturn($this->createStub(Processor::class));
        $context->method('getRequestParam')
            ->willReturnCallback(static fn(string $name): mixed => $name === 'order_id' ? $orderId : null);
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn($shipments);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')
            ->willReturnCallback(static fn(string $path, array $params): string => $path . '/' . $params['id']);
        $states = $this->createStub(ShipmentState::class);
        $states->method('toArray')
            ->willReturn([
                ShipmentInterface::STATE_UNKNOWN => 'Outcome unknown',
                ShipmentInterface::STATE_FAILED => 'Not placed',
            ]);
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('get')->willReturnCallback(function () use ($method): Order {
            if ($method === null) {
                throw new NoSuchEntityException(__('The entity that was requested doesn\'t exist.'));
            }
            /** @var Order $order */
            $order = (new ObjectManager($this))->getObject(Order::class);

            return $order->setShippingMethod($method);
        });
        $escaper = StandaloneEscaper::create();

        return new PolkurierOrderColumn(
            $context,
            $this->createStub(UiComponentFactory::class),
            new ShipmentFinder($factory),
            $url,
            $states,
            new OrderLoader($orders),
            $escaper,
            [],
            [
                'name' => 'polkurier_order',
                'config' => [],
            ],
        );
    }

    private function createShipment(int $id, int $magentoShipmentId, string $state, ?string $number): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class, ['json' => new Json()]);

        return $shipment->setId($id)->setShipmentId($magentoShipmentId)->setState($state)->setPolkurierNumber($number);
    }
}
