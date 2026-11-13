<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\PickupPoint;

use Magento\Framework\Exception\InputException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\PointFinder;
use Polkurier\Shipping\Model\PickupPoint\PointSelectionRecorder;
use Polkurier\Shipping\Model\QuotePoint;
use Polkurier\Shipping\Model\QuotePointFactory;
use Polkurier\Webservice\Dto\Response\Carrier;
use Polkurier\Webservice\Dto\Response\CourierPoint;
use Polkurier\Webservice\Enum\Courier\PointFunctionEnum;
use Polkurier\Webservice\Exception\ConnectionException;

#[CoversClass(PointSelectionRecorder::class)]
#[CoversClass(PointFinder::class)]
#[AllowMockObjectsWithoutExpectations]
class PointSelectionRecorderTest extends TestCase
{
    private ?QuotePoint $saved = null;

    /**
     * @var string[] the quote and quote address ids of the points taken away
     */
    private array $deleted = [];

    /**
     * @var string|null the method the point stored for the quote was chosen for
     */
    private ?string $stored = null;

    private bool $required = true;

    private bool $allowed = true;

    private bool $carrierOutage = false;

    private bool $pointOutage = false;

    public function testAnOpenPointThatHandsOutParcelsIsKept(): void
    {
        $this->createRecorder(new CourierPoint(
            'WAW195M',
            available: true,
            functions: [PointFunctionEnum::Collect, PointFunctionEnum::Send],
            address: 'Krótka 5, Warszawa',
        ))
            ->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW195M');

        self::assertSame('WAW195M', $this->saved?->getPointId());
        self::assertSame('Krótka 5, Warszawa', $this->saved->getPointLabel());
    }

    public function testAPointWithoutAnAddressLineIsLabelledByItsStreetAndTown(): void
    {
        $this->createRecorder(new CourierPoint(
            'WAW195M',
            street: 'Krótka 5',
            zip: '00-001',
            city: 'Warszawa',
            available: true,
            functions: [PointFunctionEnum::Collect],
        ))
            ->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW195M');

        self::assertSame('Krótka 5, 00-001 Warszawa', $this->saved?->getPointLabel());
    }

    public function testAClosedPointIsRefused(): void
    {
        $this->expectException(InputException::class);

        $this->createRecorder(
            new CourierPoint('WAW195M', available: false, functions: [PointFunctionEnum::Collect]),
        )
            ->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW195M');
    }

    public function testAPointThatOnlyTakesParcelsInIsRefused(): void
    {
        try {
            $this->createRecorder(
                new CourierPoint('WAW01N', available: true, functions: [PointFunctionEnum::Send]),
            )
                ->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW01N');
            self::fail('The customer could not collect the parcel there.');
        } catch (InputException) {
        }
        self::assertNull($this->saved);
    }

    public function testAnotherCarrierTakesTheAddresssPointAway(): void
    {
        $this->createRecorder()->select($this->quote(), 'flatrate', 'flatrate', 'WAW195M', 12);

        self::assertSame(['5/12'], $this->deleted);
        self::assertNull($this->saved);
    }

    public function testAMethodThatNeedsAPointRefusesNone(): void
    {
        $this->expectExceptionMessage('Choose a pickup point for the selected delivery method.');

        $this->createRecorder()->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', '  ');
    }

    public function testAMethodThatTakesAPointOptionallyGoesWithoutOne(): void
    {
        $this->required = false;

        $this->createRecorder()->select($this->quote(), 'polkurier', 'DPD_PICKUP', null);

        self::assertSame(['5/0'], $this->deleted);
    }

    public function testAMethodWithoutPointsDropsOneSentAnyway(): void
    {
        $this->required = false;
        $this->allowed = false;

        $this->createRecorder()->select($this->quote(), 'polkurier', 'DPD', 'WAW195M');

        self::assertSame(['5/0'], $this->deleted);
        self::assertNull($this->saved);
    }

    public function testWhenTheCarriersCantBeReadThePointIsKeptAsTyped(): void
    {
        $this->carrierOutage = true;

        $this->createRecorder()->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW195M');

        self::assertSame('WAW195M', $this->saved?->getPointId());
        self::assertSame('WAW195M', $this->saved->getPointLabel());
    }

    public function testWhenThePointCantBeLookedUpItIsKeptNotKnowingAboutCashOnDelivery(): void
    {
        $this->pointOutage = true;

        $this->createRecorder()->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW195M');

        self::assertSame('WAW195M', $this->saved?->getPointLabel());
        self::assertNull(
            $this->saved->getPointCod(),
            'Not known whether it takes cash: cash on delivery is left as it is.',
        );
    }

    public function testEachAddressOfAMultiAddressCheckoutKeepsItsOwnPoint(): void
    {
        $this->createRecorder(new CourierPoint(
            'WAW195M',
            available: true,
            functions: [PointFunctionEnum::Collect],
            cod: true,
        ))
            ->select($this->quote(), 'polkurier', 'INPOST_PACZKOMAT', 'WAW195M', 12);

        self::assertSame([5, 12], [$this->saved?->getQuoteId(), $this->saved->getQuoteAddressId()]);
        self::assertTrue($this->saved->getPointCod());
    }

    public function testPlacingNeedsAPointForTheMethodChosen(): void
    {
        $this->stored = 'INPOST_PACZKOMAT';
        $this->createRecorder()->requirePoint($this->quote(), 'INPOST_PACZKOMAT');

        $this->stored = 'INPOST_PACZKOMAT_ECONOMY';
        try {
            $this->createRecorder()->requirePoint($this->quote(), 'INPOST_PACZKOMAT');
            self::fail('A point chosen for another method doesn\'t count.');
        } catch (InputException) {
        }

        $this->required = false;
        $this->createRecorder()->requirePoint($this->quote(), 'DPD');
        $this->required = true;
        $this->carrierOutage = true;
        // Let through rather than lost.
        $this->createRecorder()->requirePoint($this->quote(), 'INPOST_PACZKOMAT');
        $this->addToAssertionCount(3);
    }

    private function createRecorder(?CourierPoint $point = null): PointSelectionRecorder
    {
        $store = $this->createStub(QuotePointRepositoryInterface::class);
        $store->method('getForQuote')->willReturnCallback(function (): ?QuotePoint {
            if ($this->stored === null) {
                return null;
            }
            /** @var QuotePoint $point */
            $point = (new ObjectManager($this))->getObject(QuotePoint::class);

            return $point->setMethodCode($this->stored);
        });
        $store->method('save')->willReturnCallback(function (QuotePoint $point): void {
            $this->saved = $point;
        });
        $store->method('deleteForQuote')->willReturnCallback(function (int $quoteId, int $addressId = 0): void {
            $this->deleted[] = $quoteId . '/' . $addressId;
        });
        $points = $this->createStub(QuotePointFactory::class);
        $points->method('create')
            ->willReturnCallback(fn(): QuotePoint => (new ObjectManager($this))->getObject(QuotePoint::class));
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarrier')->willReturnCallback(function (string $code): Carrier {
            if ($this->carrierOutage) {
                throw new ConnectionException('Timed out');
            }

            return new Carrier($code, $code, false);
        });
        $catalog->method('findPoint')->willReturnCallback(function () use ($point): CourierPoint {
            if ($this->pointOutage) {
                throw new ConnectionException('Timed out');
            }

            return $point ?? new CourierPoint(
                'WAW195M',
                available: true,
                functions: [PointFunctionEnum::Collect],
            );
        });
        $features = $this->createStub(CarrierFeatureResolver::class);
        $features->method('isRecipientPointRequired')->willReturnCallback(fn(): bool => $this->required);
        $features->method('isRecipientPointAllowed')->willReturnCallback(fn(): bool => $this->allowed);
        $features->method('getPointProviders')->willReturn(['INPOST']);
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getMapPointFunctions')->willReturn([PointFunctionEnum::Collect]);

        $logger = $this->createStub(Logger::class);

        return new PointSelectionRecorder(
            $store,
            $points,
            $catalog,
            $features,
            new PointFinder($catalog, $features, $logger),
            $config,
            $logger,
        );
    }

    private function quote(): Quote
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(5);
        $quote->method('getStoreId')->willReturn(1);

        return $quote;
    }
}
