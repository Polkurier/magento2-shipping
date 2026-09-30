<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\CarrierLabelInterfaceFactory;
use Polkurier\Shipping\Model\Carrier\CarrierConfigHydrator;
use Polkurier\Shipping\Model\Carrier\CarrierLogo;
use Polkurier\Shipping\Model\CarrierConfig;

#[CoversClass(CarrierConfigHydrator::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierConfigHydratorTest extends TestCase
{
    public function testAFixedPriceTakesADecimalComma(): void
    {
        $config = $this->config();

        $this->hydrator()->hydrate($config, ['pricing_model' => 'FIXED', 'price' => '12,99', 'active' => '1']);

        self::assertSame(['price' => 12.99], $config->getPriceInfo());
        self::assertTrue($config->isActive());
    }

    public function testTiersKeepOnlyThePricedRowsNotDeleted(): void
    {
        $config = $this->config();

        $this->hydrator()->hydrate($config, ['pricing_model' => 'TIER_SUBTOTAL', 'tiers' => [
            ['subtotal_from' => '0', 'price' => '14.99'],
            ['subtotal_from' => '100', 'price' => ''],
            ['subtotal_from' => '250', 'price' => '0', 'delete' => '1'],
        ]]);

        self::assertSame(['tiers' => [['subtotal_from' => 0, 'weight_from' => 0, 'price' => 14.99]]], $config->getPriceInfo());
    }

    public function testANegativePriceIsRefusedOnThePageAndInTheGrid(): void
    {
        foreach ([
            fn() => $this->hydrator()->hydrate($this->config(), ['pricing_model' => 'FIXED', 'price' => '-1']),
            fn() => $this->hydrator()->applyRow($this->config()->setPricingModel('FIXED'), ['price' => '-1']),
        ] as $save) {
            try {
                $save();
                self::fail('A negative price is refused.');
            } catch (LocalizedException $e) {
                self::assertSame('Enter the price.', $e->getMessage());
            }
        }
    }

    public function testTheGridCannotSetOnePriceForACarrierPricedInTiers(): void
    {
        $this->expectException(LocalizedException::class);

        $this->hydrator()->applyRow($this->config()->setPricingModel('TIER_WEIGHT'), ['price' => '10']);
    }

    private function config(): CarrierConfig
    {
        /** @var CarrierConfig $config */
        $config = (new ObjectManager($this))->getObject(CarrierConfig::class);

        return $config->setWebsiteId(1);
    }

    private function hydrator(): CarrierConfigHydrator
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([]);

        return new CarrierConfigHydrator($this->createStub(CarrierLabelInterfaceFactory::class), $storeManager, $this->createStub(CarrierLogo::class));
    }
}
