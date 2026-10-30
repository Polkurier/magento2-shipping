<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Sales;

use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Status\TrackAdoptionService;
use Polkurier\Shipping\Plugin\Sales\ShipmentTrackRepositoryPlugin;

#[CoversClass(ShipmentTrackRepositoryPlugin::class)]
class ShipmentTrackRepositoryPluginTest extends TestCase
{
    public function testANumberAddedToAMagentoShipmentIsLinked(): void
    {
        $adoption = $this->createMock(TrackAdoptionService::class);
        $adoption->expects(self::once())->method('adopt')->with(40, ['WB-1001']);
        $track = $this->track('WB-1001', 'custom');

        $result = (new ShipmentTrackRepositoryPlugin($adoption))
            ->afterSave($this->createStub(ShipmentTrackRepositoryInterface::class), $track);

        self::assertSame($track, $result);
    }

    public function testTheModulesOwnTrackIsSkipped(): void
    {
        $adoption = $this->createMock(TrackAdoptionService::class);
        $adoption->expects(self::never())->method('adopt');

        (new ShipmentTrackRepositoryPlugin($adoption))
            ->afterSave($this->createStub(ShipmentTrackRepositoryInterface::class), $this->track('WB-1', 'polkurier'));
    }

    private function track(string $number, string $carrierCode): ShipmentTrackInterface
    {
        $track = $this->createStub(ShipmentTrackInterface::class);
        $track->method('getTrackNumber')->willReturn($number);
        $track->method('getCarrierCode')->willReturn($carrierCode);
        $track->method('getParentId')->willReturn(40);

        return $track;
    }
}
