<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier\Pricing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\Pricing\FixedPriceCalculator;
use Polkurier\Shipping\Model\Carrier\Pricing\PriceCalculatorPool;
use Polkurier\Shipping\Model\Carrier\Pricing\TieredPriceCalculator;

#[CoversClass(TieredPriceCalculator::class)]
#[CoversClass(FixedPriceCalculator::class)]
#[CoversClass(PriceCalculatorPool::class)]
class TieredPriceCalculatorTest extends TestCase
{
    public function testSubtotalTiersPickTheHighestThresholdReached(): void
    {
        $config = $this->config('TIER_SUBTOTAL', ['tiers' => [
            ['subtotal_from' => 0, 'price' => 14.99],
            ['subtotal_from' => 200, 'price' => 0],
            ['subtotal_from' => 100, 'price' => 9.99],
        ]]);
        $tiers = new TieredPriceCalculator(bySubtotal: true, byWeight: false);

        self::assertSame(14.99, $tiers->calculate($config, 99.99, 30));
        self::assertSame(9.99, $tiers->calculate($config, 100, 30));
        self::assertSame(0.0, $tiers->calculate($config, 250, 30));
    }

    public function testCartBelowEveryTierIsNotPriced(): void
    {
        $config = $this->config('TIER_WEIGHT', ['tiers' => [['weight_from' => 1, 'price' => 20]]]);

        self::assertNull((new TieredPriceCalculator(false, true))->calculate($config, 500, 0.5));
    }

    public function testMatrixPrefersSubtotalThenWeight(): void
    {
        $config = $this->config('TIER_SUBTOTAL_WEIGHT', ['tiers' => [
            ['subtotal_from' => 0, 'weight_from' => 0, 'price' => 15],
            ['subtotal_from' => 0, 'weight_from' => 10, 'price' => 30],
            ['subtotal_from' => 300, 'weight_from' => 0, 'price' => 0],
            ['subtotal_from' => 300, 'weight_from' => 10, 'price' => 10],
        ]]);
        $tiers = new TieredPriceCalculator(true, true);

        self::assertSame(15.0, $tiers->calculate($config, 100, 5));
        self::assertSame(30.0, $tiers->calculate($config, 100, 12));
        self::assertSame(0.0, $tiers->calculate($config, 300, 5));
        self::assertSame(10.0, $tiers->calculate($config, 300, 12));
    }

    public function testOfTwoTiersWithTheSameThresholdTheFirstCounts(): void
    {
        $config = $this->config('TIER_SUBTOTAL', ['tiers' => [
            ['subtotal_from' => 100, 'price' => 9.99],
            ['subtotal_from' => 100, 'price' => 4.99],
        ]]);

        self::assertSame(9.99, (new TieredPriceCalculator(true, false))->calculate($config, 150, 1));
    }

    public function testRowsThatArentTiersAreLeftOut(): void
    {
        $config = $this->config('TIER_SUBTOTAL', ['tiers' => [
            'not a row',
            ['subtotal_from' => 0],
            ['subtotal_from' => 50, 'price' => 'free'],
            ['subtotal_from' => 10, 'price' => '12.50'],
        ]]);

        self::assertSame(12.5, (new TieredPriceCalculator(true, false))->calculate($config, 100, 1));
        self::assertNull(
            (new TieredPriceCalculator(true, false))->calculate(
                $this->config('TIER_SUBTOTAL', ['tiers' => 'none']),
                100,
                1,
            ),
        );
    }

    public function testANegativePriceStoredAnywayPricesNothing(): void
    {
        $config = $this->config('TIER_SUBTOTAL', ['tiers' => [['subtotal_from' => 0, 'price' => -5]]]);

        self::assertNull((new TieredPriceCalculator(true, false))->calculate($config, 100, 1));
    }

    public function testPoolDispatchesByPricingModelAndRefusesUnknownOnes(): void
    {
        $pool = new PriceCalculatorPool($this->createStub(Logger::class), ['FIXED' => new FixedPriceCalculator()]);

        self::assertSame(12.5, $pool->calculate($this->config('FIXED', ['price' => '12.5']), 1, 1));
        self::assertNull($pool->calculate($this->config('FIXED', []), 1, 1));
        self::assertNull($pool->calculate($this->config('NO_SUCH_MODEL', ['price' => 1]), 1, 1));
    }

    /**
     * @param array<string, mixed> $priceInfo
     */
    private function config(string $model, array $priceInfo): CarrierConfigInterface
    {
        return $this->createConfiguredStub(CarrierConfigInterface::class, [
            'getPricingModel' => $model,
            'getPriceInfo' => $priceInfo,
        ]);
    }
}
