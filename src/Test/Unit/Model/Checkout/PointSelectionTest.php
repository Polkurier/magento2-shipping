<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Checkout;

use Magento\Framework\Exception\InputException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Checkout\PointSelection;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\QuotePoint;
use Polkurier\Shipping\Model\QuotePointFactory;
use Polkurier\Webservice\Enum\Courier\PointFunctionEnum;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\CourierPoint;
use Psr\Log\NullLogger;

#[CoversClass(PointSelection::class)]
#[AllowMockObjectsWithoutExpectations]
class PointSelectionTest extends TestCase
{
    private ?QuotePoint $saved = null;

    public function testAnOpenPointThatHandsOutParcelsIsKept(): void
    {
        $this->selection(new CourierPoint('WAW195M', available: true, functions: [PointFunctionEnum::Collect, PointFunctionEnum::Send], address: 'Krótka 5, Warszawa'))
            ->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW195M');

        self::assertSame('WAW195M', $this->saved?->getPointId());
        self::assertSame('Krótka 5, Warszawa', $this->saved->getPointLabel());
    }

    public function testAClosedPointIsRefused(): void
    {
        $this->expectException(InputException::class);

        $this->selection(new CourierPoint('WAW195M', available: false, functions: [PointFunctionEnum::Collect]))
            ->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW195M');
    }

    public function testAPointThatOnlyTakesParcelsInIsRefused(): void
    {
        try {
            $this->selection(new CourierPoint('WAW01N', available: true, functions: [PointFunctionEnum::Send]))
                ->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW01N');
            self::fail('The customer could not collect the parcel there.');
        } catch (InputException) {
        }
        self::assertNull($this->saved);
    }

    private function selection(CourierPoint $point): PointSelection
    {
        $store = $this->createStub(OrderPointStore::class);
        $store->method('getForQuote')->willReturn(null);
        $store->method('saveForQuote')->willReturnCallback(function (QuotePoint $point): void {
            $this->saved = $point;
        });
        $points = $this->createStub(QuotePointFactory::class);
        $points->method('create')->willReturnCallback(fn(): QuotePoint => (new ObjectManager($this))->getObject(QuotePoint::class));
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')->willReturn(new Carrier('INPOST_PACZKOMAT', 'Paczkomat', false));
        $catalog->method('findPoint')->willReturn($point);
        $features = $this->createStub(CarrierFeatures::class);
        $features->method('isRecipientPointRequired')->willReturn(true);
        $features->method('isRecipientPointAllowed')->willReturn(true);
        $features->method('getPointProviders')->willReturn(['INPOST']);
        $config = $this->createStub(Config::class);
        $config->method('getMapPointFunctions')->willReturn([PointFunctionEnum::Collect]);

        return new PointSelection($store, $points, $catalog, $features, $config, new NullLogger());
    }

    private function quote(): Quote
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(5);
        $quote->method('getStoreId')->willReturn(1);

        return $quote;
    }
}
