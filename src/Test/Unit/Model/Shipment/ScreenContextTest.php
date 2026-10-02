<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\Shipment\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\OrderAmounts;
use Polkurier\Shipping\Model\Shipment\ScreenContext;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\PointsMap;
use Polkurier\Webservice\Response\CourierPoint;

#[CoversClass(ScreenContext::class)]
#[AllowMockObjectsWithoutExpectations]
class ScreenContextTest extends TestCase
{
    public function testAnOpenPointComesBackAsPolkurierHasItNow(): void
    {
        $point = new CourierPoint('WAW195M', available: true, address: '02-134 Warszawa, 1 Sierpnia 36B');

        self::assertSame($point, $this->context($point)->findRecipientPoint('INPOST_PACZKOMAT', 'WAW195M'));
    }

    public function testAPointGoneOrClosedIsSaidToBe(): void
    {
        self::assertFalse($this->context(null)->findRecipientPoint('INPOST_PACZKOMAT', 'WAW299X'), 'No longer there.');
        self::assertFalse($this->context(new CourierPoint('WAW195M', available: false))->findRecipientPoint('INPOST_PACZKOMAT', 'WAW195M'), 'Closed.');
    }

    public function testWhatCantBeToldIsNeverTakenAsClosed(): void
    {
        self::assertNull($this->context(new ConnectionException('cURL error 7'))->findRecipientPoint('INPOST_PACZKOMAT', 'WAW195M'), 'Polkurier unreachable.');
        self::assertNull($this->context(null)->findRecipientPoint('INPOST_PACZKOMAT', ''), 'No point.');
        self::assertNull($this->context(null, carrier: false)->findRecipientPoint('GONE', 'WAW195M'), 'No such carrier.');
    }

    private function context(CourierPoint|\Throwable|null $found, bool $carrier = true): ScreenContext
    {
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')->willReturn($carrier ? new Carrier('INPOST_PACZKOMAT', 'Paczkomat', false, new AdditionalData(
            pointsMap: new PointsMap(['inpost'], recipientPoint: PointRequirementEnum::Required),
        )) : null);
        if ($found instanceof \Throwable) {
            $catalog->method('findPoint')->willThrowException($found);
        } else {
            $catalog->method('findPoint')->willReturn($found);
        }

        return new ScreenContext(
            $this->createStub(ShipmentManagementInterface::class),
            $this->createStub(DraftHydrator::class),
            $this->createStub(MasterData::class),
            $this->createStub(PackType::class),
            $this->createStub(MapTokenProvider::class),
            $this->createStub(Config::class),
            $this->createStub(OrderAmounts::class),
            $this->createStub(UrlInterface::class),
            $catalog,
            new CarrierFeatures(),
            $this->createStub(DefaultDraftBuilder::class),
        );
    }
}
