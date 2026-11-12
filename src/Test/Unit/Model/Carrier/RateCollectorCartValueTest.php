<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Item;
use Magento\Shipping\Model\Rate\ResultFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Enum\Config\CartValueEnum;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\Pricing\PriceCalculatorInterface;
use Polkurier\Shipping\Model\Carrier\RateCollector;
use Polkurier\Shipping\Model\Carrier\WeightUnitConverter;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;

#[CoversClass(RateCollector::class)]
#[CoversClass(CartValueEnum::class)]
#[AllowMockObjectsWithoutExpectations]
class RateCollectorCartValueTest extends TestCase
{
    private ?string $basis = null;
    private bool $discountOnGross = false;

    public function testTheCartIsValuedWithTaxByDefault(): void
    {
        self::assertSame(
            123.0 + 61.5,
            $this->calculateValue([$this->createItem(100.0, 123.0), $this->createItem(50.0, 61.5)]),
        );
    }

    public function testTheCartIsValuedWithoutTaxWhenSet(): void
    {
        $this->basis = 'net';

        self::assertSame(
            150.0,
            $this->calculateValue([$this->createItem(100.0, 123.0), $this->createItem(50.0, 61.5)]),
        );
    }

    /**
     * @return array<string, array{string, bool, float}>
     */
    public static function provideDiscounts(): array
    {
        // A 20.00 discount on a 100.00 net, 123.00 gross row: a fifth of the net price, or a bit
        // over a sixth of the gross one, taken off both values alike.
        return [
            'on net prices, valued with tax' => ['gross', false, 98.4],
            'on net prices, valued without tax' => ['net', false, 80.0],
            'on gross prices, valued with tax' => ['gross', true, 103.0],
            'on gross prices, valued without tax' => ['net', true, 83.7398],
        ];
    }

    #[DataProvider('provideDiscounts')]
    public function testADiscountIsTakenOffInProportion(string $basis, bool $discountOnGross, float $expected): void
    {
        $this->basis = $basis;
        $this->discountOnGross = $discountOnGross;

        self::assertSame($expected, $this->calculateValue([$this->createItem(100.0, 123.0, 20.0)]));
    }

    public function testADiscountNeverTakesTheValueBelowNothing(): void
    {
        self::assertSame(0.0, $this->calculateValue([$this->createItem(10.0, 12.3, 50.0)]));
    }

    public function testTheChildrenOfAProductAreCountedInItsOwnRow(): void
    {
        $parent = $this->createItem(100.0, 123.0, 10.0);
        $child = $this->createItem(100.0, 123.0, 10.0);
        $child->setParentItem($parent);

        self::assertSame(110.7, $this->calculateValue([$parent, $child]));
    }

    public function testARowWithoutTaxWorkedOutCountsTheSameBothWays(): void
    {
        self::assertSame(100.0, $this->calculateValue([$this->createItem(100.0, null)]));
    }

    public function testARequestWithoutItemsTakesMagentosPackageValue(): void
    {
        $request = new RateRequest(['store_id' => 1, 'package_value_with_discount' => 42.5]);

        self::assertSame(42.5, $this->rateCollector()->getCartValue($request));
    }

    /**
     * @param Item[] $items
     */
    private function calculateValue(array $items): float
    {
        return $this->rateCollector()->getCartValue(new RateRequest(['store_id' => 1, 'all_items' => $items]));
    }

    private function createItem(float $rowTotal, ?float $rowTotalInclTax, float $discount = 0.0): Item
    {
        /** @var Item $item */
        $item = (new ObjectManager($this))->getObject(Item::class);
        $item->setData([
            'base_row_total' => $rowTotal,
            'base_row_total_incl_tax' => $rowTotalInclTax,
            'base_discount_amount' => $discount,
        ]);

        return $item;
    }

    private function rateCollector(): RateCollector
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            fn(string $path): ?string => $path === ConfigProvider::XML_PATH_TIER_CART_VALUE ? $this->basis : null,
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn(string $path): bool => $path === 'tax/calculation/discount_tax' && $this->discountOnGross,
        );

        return new RateCollector(
            $this->createStub(ResultFactory::class),
            $this->createStub(MethodFactory::class),
            $this->createStub(CarrierConfigProvider::class),
            $this->createStub(CarrierCatalog::class),
            new CarrierFeatureResolver(),
            $this->createStub(PriceCalculatorInterface::class),
            $this->createStub(WeightUnitConverter::class),
            $this->createStub(Logger::class),
            $this->createStub(MapTokenProvider::class),
            $this->createStub(State::class),
            new ConfigProvider($scopeConfig, $this->createStub(EncryptorInterface::class)),
            $scopeConfig,
        );
    }
}
