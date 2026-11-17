<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Listing\Shipment;

use Magento\Framework\DataObject;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Collection;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Test\Unit\Support\StandaloneEscaper;
use Polkurier\Shipping\Ui\Component\Listing\Shipment\MagentoShipmentLinkColumn;

#[CoversClass(MagentoShipmentLinkColumn::class)]
#[AllowMockObjectsWithoutExpectations]
class MagentoShipmentLinkColumnTest extends TestCase
{
    public function testAShipmentLinksToItsMagentoShipment(): void
    {
        self::assertSame(
            ['<a href="adminhtml/order_shipment/view?shipment_id=7">#000000027</a>', ''],
            $this->renderCells([7, null]),
            'One that went on none shows nothing.',
        );
    }

    /**
     * @param array<int|null> $shipmentIds the Magento shipment of each row
     *
     * @return string[] the cells
     */
    private function renderCells(array $shipmentIds): array
    {
        $context = $this->createStub(ContextInterface::class);
        $context->method('getProcessor')->willReturn($this->createStub(Processor::class));
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getItems')->willReturn([new DataObject(['id' => 7, 'increment_id' => '000000027'])]);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($collection);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $path, array $params): string => $path . '?' . \http_build_query($params),
        );
        $column = new MagentoShipmentLinkColumn(
            $context,
            $this->createStub(UiComponentFactory::class),
            $collections,
            $url,
            StandaloneEscaper::create(),
            [],
            ['name' => 'magento_shipment'],
        );

        $source = $column->prepareDataSource(['data' => ['items' => \array_map(
            static fn(?int $id): array => ['shipment_id' => $id],
            $shipmentIds,
        )]]);

        return \array_column($source['data']['items'], 'magento_shipment');
    }
}
