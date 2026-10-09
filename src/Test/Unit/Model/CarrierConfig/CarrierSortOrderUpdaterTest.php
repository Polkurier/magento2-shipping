<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\CarrierConfig;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\CarrierConfig\CarrierSortOrderUpdater;

#[CoversClass(CarrierSortOrderUpdater::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierSortOrderUpdaterTest extends TestCase
{
    /**
     * @var array<string, CarrierConfig> by carrier code, in their current order
     */
    private array $configs = [];

    /**
     * @var string[] the carriers saved, in the order saved
     */
    private array $saved = [];

    public function testTheRowsTakeTheirNewOrderAndEveryCarrierIsNumberedByTens(): void
    {
        $this->order()->arrange([4, 1, 2, 3]);

        self::assertSame(['UPS' => 10, 'DPD' => 20, 'GLS' => 30, 'INPOST' => 40], $this->sortOrders());
    }

    public function testCarriersTheGridDidNotShowKeepTheirPlaces(): void
    {
        // Filtered to DPD and INPOST, which swap: GLS and UPS stay second and fourth.
        $this->order()->arrange([3, 1]);

        self::assertSame(['INPOST' => 10, 'GLS' => 20, 'DPD' => 30, 'UPS' => 40], $this->sortOrders());
    }

    public function testOnlyCarriersWhoseNumberChangesAreSaved(): void
    {
        $this->configs['DPD']->setSortOrder(10);
        $this->configs['GLS']->setSortOrder(20);

        $this->order()->arrange([1, 2, 3, 4]);

        self::assertSame([], $this->saved);
    }

    public function testUnknownRowsAloneAreRefused(): void
    {
        $this->expectException(LocalizedException::class);

        $this->order()->arrange([99]);
    }

    protected function setUp(): void
    {
        foreach (['DPD' => 0, 'GLS' => 0, 'INPOST' => 30, 'UPS' => 40] as $code => $sortOrder) {
            /** @var CarrierConfig $config */
            $config = (new ObjectManager($this))->getObject(CarrierConfig::class);
            $config->setId(\count($this->configs) + 1);
            $this->configs[$code] = $config->setCarrierCode($code)->setSortOrder($sortOrder);
        }
    }

    /**
     * @return array<string, int> by carrier code, in order
     */
    private function sortOrders(): array
    {
        $orders = \array_map(static fn(CarrierConfigInterface $config): int => $config->getSortOrder(), $this->configs);
        \asort($orders);

        return $orders;
    }

    private function order(): CarrierSortOrderUpdater
    {
        $configs = $this->createStub(CarrierConfigProvider::class);
        $configs->method('getAll')->willReturnCallback(fn(): array => $this->configs);
        $repository = $this->createStub(CarrierConfigRepositoryInterface::class);
        $repository->method('save')
            ->willReturnCallback(function (CarrierConfigInterface $config): CarrierConfigInterface {
                $this->saved[] = $config->getCarrierCode();

                return $config;
            });

        return new CarrierSortOrderUpdater($configs, $repository);
    }
}
