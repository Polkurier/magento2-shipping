<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Screen;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Shipping\Model\Shipment\Draft\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Screen\ScreenDataProvider;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\PointsMap;
use Polkurier\Webservice\Response\CourierPoint;

#[CoversClass(ScreenDataProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class ScreenDataProviderTest extends TestCase
{
    public function testAnOpenPointComesBackAsPolkurierHasItNow(): void
    {
        $point = new CourierPoint('WAW195M', available: true, address: '02-134 Warszawa, 1 Sierpnia 36B');

        self::assertSame($point, $this->context($point)->findRecipientPoint('INPOST_PACZKOMAT', 'WAW195M'));
    }

    public function testAPointGoneOrClosedIsSaidToBe(): void
    {
        self::assertFalse(
            $this->context(null)->findRecipientPoint('INPOST_PACZKOMAT', 'WAW299X'),
            'No longer there.',
        );
        self::assertFalse(
            $this->context(new CourierPoint('WAW195M', available: false))
                ->findRecipientPoint('INPOST_PACZKOMAT', 'WAW195M'),
            'Closed.',
        );
    }

    public function testWhatCantBeToldIsNeverTakenAsClosed(): void
    {
        self::assertNull(
            $this->context(new ConnectionException('cURL error 7'))
                ->findRecipientPoint('INPOST_PACZKOMAT', 'WAW195M'),
            'Polkurier unreachable.',
        );
        self::assertNull($this->context(null)->findRecipientPoint('INPOST_PACZKOMAT', ''), 'No point.');
        self::assertNull(
            $this->context(null, carrier: false)->findRecipientPoint('GONE', 'WAW195M'),
            'No such carrier.',
        );
    }

    public function testThePointIsDescribedByItsAddressTodayOrAsTheCustomerChoseIt(): void
    {
        $open = new CourierPoint('WAW195M', available: true, address: '02-134 Warszawa, 1 Sierpnia 36B');

        self::assertSame(
            ['id' => 'WAW195M', 'label' => '02-134 Warszawa, 1 Sierpnia 36B', 'closed' => false],
            $this->context($open)->describeRecipientPoint(42, 'INPOST_PACZKOMAT', 'WAW195M'),
        );
        self::assertSame(
            ['id' => 'WAW195M', 'label' => 'Paczkomat WAW195M, as chosen', 'closed' => true],
            $this->context(null)->describeRecipientPoint(42, 'INPOST_PACZKOMAT', 'WAW195M'),
            'Gone since: told by the label the customer chose it by.',
        );
        self::assertSame(
            ['id' => 'WAW299X', 'label' => '', 'closed' => true],
            $this->context(null)->describeRecipientPoint(42, 'INPOST_PACZKOMAT', 'WAW299X'),
            'Another point than the customer\'s has no label of theirs.',
        );
    }

    private function context(CourierPoint|\Throwable|null $found, bool $carrier = true): ScreenDataProvider
    {
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')->willReturn(
            $carrier
                ? new Carrier('INPOST_PACZKOMAT', 'Paczkomat', false, new AdditionalData(
                    pointsMap: new PointsMap(['inpost'], recipientPoint: PointRequirementEnum::Required),
                ))
                : null,
        );
        if ($found instanceof \Throwable) {
            $catalog->method('findPoint')->willThrowException($found);
        } else {
            $catalog->method('findPoint')->willReturn($found);
        }

        $chosen = $this->createStub(OrderPointInterface::class);
        $chosen->method('getPointId')->willReturn('WAW195M');
        $chosen->method('getPointLabel')->willReturn('Paczkomat WAW195M, as chosen');
        $points = $this->createStub(OrderPointRepositoryInterface::class);
        $points->method('getForOrder')->willReturn($chosen);

        return new ScreenDataProvider(
            $this->createStub(ShipmentManagementInterface::class),
            $this->createStub(DraftHydrator::class),
            $this->createStub(PackTemplateRepositoryInterface::class),
            $this->createStub(AddressRepositoryInterface::class),
            $this->createStub(PackType::class),
            $this->createStub(MapTokenProvider::class),
            $this->createStub(ConfigProvider::class),
            $this->createStub(OrderAmountCalculator::class),
            $catalog,
            new CarrierFeatureResolver(),
            $this->createStub(DefaultDraftBuilder::class),
            $points,
        );
    }
}
