<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\CarrierConfig;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\CarrierLabelInterfaceFactory;
use Polkurier\Shipping\Model\Carrier\CarrierLogoStorage;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigHydrator;

#[CoversClass(CarrierConfigHydrator::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierConfigHydratorTest extends TestCase
{
    public function testAFixedPriceTakesADecimalComma(): void
    {
        $config = $this->createConfig();

        $this->createHydrator()->hydrate($config, ['pricing_model' => 'FIXED', 'price' => '12,99', 'active' => '1']);

        self::assertSame(['price' => 12.99], $config->getPriceInfo());
        self::assertTrue($config->isActive());
    }

    public function testTiersKeepOnlyThePricedRowsNotDeleted(): void
    {
        $config = $this->createConfig();

        $this->createHydrator()->hydrate($config, ['pricing_model' => 'TIER_SUBTOTAL', 'tiers' => [
            ['subtotal_from' => '0', 'price' => '14.99'],
            ['subtotal_from' => '100', 'price' => ''],
            ['subtotal_from' => '250', 'price' => '0', 'delete' => '1'],
        ]]);

        self::assertSame(
            ['tiers' => [['subtotal_from' => 0, 'weight_from' => 0, 'price' => 14.99]]],
            $config->getPriceInfo(),
        );
    }

    public function testANegativePriceIsRefusedOnThePageAndInTheGrid(): void
    {
        $saves = [
            fn() => $this->createHydrator()->hydrate(
                $this->createConfig(),
                ['pricing_model' => 'FIXED', 'price' => '-1'],
            ),
            fn() => $this->createHydrator()->applyRow(
                $this->createConfig()->setPricingModel('FIXED'),
                ['price' => '-1'],
            ),
        ];
        foreach ($saves as $save) {
            try {
                $save();
                self::fail('A negative price is refused.');
            } catch (LocalizedException $e) {
                self::assertSame('Enter the price.', $e->getMessage());
            }
        }
    }

    public function testATierWithANegativePriceOrThresholdIsRefused(): void
    {
        $negativeTiers = [
            ['subtotal_from' => '0', 'price' => '-5'],
            ['subtotal_from' => '-10', 'price' => '5'],
            ['weight_from' => '-1', 'price' => '5'],
        ];
        foreach ($negativeTiers as $tier) {
            try {
                $this->createHydrator()->hydrate($this->createConfig(), [
                    'pricing_model' => 'TIER_SUBTOTAL_WEIGHT',
                    'tiers' => [$tier],
                ]);
                self::fail('A negative tier is refused.');
            } catch (LocalizedException $e) {
                self::assertSame('A price tier can\'t have a negative price or threshold.', $e->getMessage());
            }
        }
    }

    public function testTheGridCannotSetOnePriceForACarrierPricedInTiers(): void
    {
        $this->expectException(LocalizedException::class);

        $this->createHydrator()->applyRow($this->createConfig()->setPricingModel('TIER_WEIGHT'), ['price' => '10']);
    }

    private function createConfig(): CarrierConfig
    {
        /** @var CarrierConfig $config */
        $config = (new ObjectManager($this))->getObject(CarrierConfig::class, ['json' => new Json()]);

        return $config;
    }

    private function createHydrator(): CarrierConfigHydrator
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([]);

        return new CarrierConfigHydrator(
            $this->createStub(CarrierLabelInterfaceFactory::class),
            $storeManager,
            $this->createStub(CarrierLogoStorage::class),
        );
    }
}
