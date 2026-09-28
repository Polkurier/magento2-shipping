<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Pricing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Pricing\Fixed;
use Polkurier\Shipping\Model\Pricing\PriceCalculatorPool;
use Polkurier\Shipping\Model\Pricing\RateContext;
use Polkurier\Shipping\Model\Pricing\Tiers;

#[CoversClass(Tiers::class)]
#[CoversClass(Fixed::class)]
#[CoversClass(PriceCalculatorPool::class)]
class TiersTest extends TestCase
{
    public function testSubtotalTiersPickTheHighestThresholdReached(): void
    {
        $config = $this->config('TIER_SUBTOTAL', ['tiers' => [
            ['subtotal_from' => 0, 'price' => 14.99],
            ['subtotal_from' => 200, 'price' => 0],
            ['subtotal_from' => 100, 'price' => 9.99],
        ]]);
        $tiers = new Tiers(bySubtotal: true, byWeight: false);

        self::assertSame(14.99, $tiers->calculate($config, new RateContext(99.99, 30)));
        self::assertSame(9.99, $tiers->calculate($config, new RateContext(100, 30)));
        self::assertSame(0.0, $tiers->calculate($config, new RateContext(250, 30)));
    }

    public function testCartBelowEveryTierIsNotPriced(): void
    {
        $config = $this->config('TIER_WEIGHT', ['tiers' => [['weight_from' => 1, 'price' => 20]]]);

        self::assertNull((new Tiers(false, true))->calculate($config, new RateContext(500, 0.5)));
    }

    public function testMatrixPrefersSubtotalThenWeight(): void
    {
        $config = $this->config('TIER_SUBTOTAL_WEIGHT', ['tiers' => [
            ['subtotal_from' => 0, 'weight_from' => 0, 'price' => 15],
            ['subtotal_from' => 0, 'weight_from' => 10, 'price' => 30],
            ['subtotal_from' => 300, 'weight_from' => 0, 'price' => 0],
            ['subtotal_from' => 300, 'weight_from' => 10, 'price' => 10],
        ]]);
        $tiers = new Tiers(true, true);

        self::assertSame(15.0, $tiers->calculate($config, new RateContext(100, 5)));
        self::assertSame(30.0, $tiers->calculate($config, new RateContext(100, 12)));
        self::assertSame(0.0, $tiers->calculate($config, new RateContext(300, 5)));
        self::assertSame(10.0, $tiers->calculate($config, new RateContext(300, 12)));
    }

    public function testPoolDispatchesByPricingModelAndRefusesUnknownOnes(): void
    {
        $pool = new PriceCalculatorPool(['FIXED' => new Fixed()]);

        self::assertSame(12.5, $pool->calculate($this->config('FIXED', ['price' => '12.5']), new RateContext(1, 1)));
        self::assertNull($pool->calculate($this->config('FIXED', []), new RateContext(1, 1)));
        self::assertNull($pool->calculate($this->config('NO_SUCH_MODEL', ['price' => 1]), new RateContext(1, 1)));
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
