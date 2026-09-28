<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\OrderSnapshot;

#[CoversClass(OrderSnapshot::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderSnapshotTest extends TestCase
{
    private const string NOW = '2026-09-25 12:00:00';

    /**
     * @return array<string, array{string, ?string, ?string, bool}>
     */
    public static function shipments(): array
    {
        return [
            'in transit' => ['WP', null, '2026-09-01 10:00:00', false],
            'delivered without COD' => ['D', null, '2026-09-24 10:00:00', true],
            'delivered, COD still with the carrier' => ['D', 'IN_TRANSIT', '2026-09-24 10:00:00', false],
            'delivered, COD in the money box' => ['D', 'MONEY_BOX', '2026-09-24 10:00:00', false],
            'delivered, COD paid out' => ['D', 'PAID', '2026-09-24 10:00:00', true],
            'delivered, COD offset' => ['D', 'COMPENSATION', '2026-09-24 10:00:00', true],
            'returning since yesterday' => ['Z', 'WAITING', '2026-09-24 10:00:00', false],
            'returning for 15 days' => ['Z', 'WAITING', '2026-09-10 10:00:00', true],
            'returning, no status date' => ['Z', null, null, false],
            'carrier exception' => ['W', null, '2026-08-01 10:00:00', false],
        ];
    }

    #[DataProvider('shipments')]
    public function testDecidesWhenAShipmentIsFinished(string $status, ?string $cod, ?string $statusDate, bool $final): void
    {
        $shipment = $this->shipment()->setStatusCode($status)->setCodStatus($cod)->setStatusDate($statusDate);

        self::assertSame($final, (new OrderSnapshot())->isFinal($shipment, new \DateTimeImmutable(self::NOW)));
    }

    public function testACancelledShipmentIsFinished(): void
    {
        $shipment = $this->shipment()->setState(ShipmentInterface::STATE_CANCELLED)->setStatusCode('OC');

        self::assertTrue((new OrderSnapshot())->isFinal($shipment));
    }

    private function shipment(): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);

        return $shipment->setState(ShipmentInterface::STATE_CREATED);
    }
}
