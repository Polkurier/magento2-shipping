<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Sales;

use Magento\Sales\Api\Data\ShipmentTrackCreationInterface;
use Magento\Sales\Api\ShipOrderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Status\TrackAdoptionService;
use Polkurier\Shipping\Plugin\Sales\ShipOrderPlugin;

#[CoversClass(ShipOrderPlugin::class)]
class ShipOrderPluginTest extends TestCase
{
    public function testTheNumbersAMagentoShipmentIsMadeWithAreLinkedExceptTheModulesOwn(): void
    {
        $adoption = $this->createMock(TrackAdoptionService::class);
        $adoption->expects(self::once())->method('adopt')->with(40, ['WB-1001']);

        $result = (new ShipOrderPlugin($adoption))->afterExecute(
            $this->createStub(ShipOrderInterface::class),
            '40',
            31,
            tracks: [$this->track('WB-1001', 'custom'), $this->track('WB-1', 'polkurier')],
        );

        self::assertSame('40', $result);
    }

    public function testAMagentoShipmentMadeWithoutNumbersLinksNothing(): void
    {
        $adoption = $this->createMock(TrackAdoptionService::class);
        $adoption->expects(self::never())->method('adopt');

        (new ShipOrderPlugin($adoption))->afterExecute(
            $this->createStub(ShipOrderInterface::class),
            40,
            31,
            tracks: [$this->track('WB-1', 'polkurier')],
        );
    }

    private function track(string $number, string $carrierCode): ShipmentTrackCreationInterface
    {
        $track = $this->createStub(ShipmentTrackCreationInterface::class);
        $track->method('getTrackNumber')->willReturn($number);
        $track->method('getCarrierCode')->willReturn($carrierCode);

        return $track;
    }
}
