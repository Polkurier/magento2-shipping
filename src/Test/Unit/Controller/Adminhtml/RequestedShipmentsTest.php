<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\Component\MassAction\Filter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\RequestedShipments;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

#[CoversClass(RequestedShipments::class)]
#[AllowMockObjectsWithoutExpectations]
class RequestedShipmentsTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, int[]}>
     */
    public static function idParams(): iterable
    {
        yield 'a comma-separated list' => ['4,5,,x', [4, 5]];
        yield 'one' => [7, [7]];
        yield 'a list' => [['4', '5,6'], [4, 5, 6]];
        yield 'a list with a list in it' => [['4', ['5']], [4]];
        yield 'none' => [null, []];
    }

    /**
     * @param int[] $expected
     */
    #[DataProvider('idParams')]
    public function testTheIdsAreReadFromAListOrAString(mixed $param, array $expected): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn(string $name): mixed => $name === 'ids' ? $param : null);

        $requested = new RequestedShipments(
            $this->createStub(ShipmentRepositoryInterface::class),
            $this->createStub(ShipmentFinder::class),
            $this->createStub(CollectionFactory::class),
            $this->createStub(Filter::class),
        );

        self::assertSame($expected, $requested->getIds($request));
    }
}
