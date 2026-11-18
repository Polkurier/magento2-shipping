<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;

#[CoversClass(CarrierNameProvider::class)]
class CarrierNameProviderTest extends TestCase
{
    public function testACarrierIsShownByItsName(): void
    {
        self::assertSame('InPost Paczkomat 24/7', $this->createProvider()->get('INPOST_PACZKOMAT'));
    }

    public function testOnlyACarrierTheModuleKnowsNothingOfByItsCode(): void
    {
        self::assertSame('GLS', $this->createProvider()->get('GLS'));
        self::assertSame(
            'POCZTEX',
            $this->createProvider()->get('POCZTEX'),
            'Nameless settings: the code is all there is.',
        );
    }

    public function testEveryCarrierInAlphabeticalOrderOfName(): void
    {
        self::assertSame(
            ['DPD' => 'DPD', 'INPOST_PACZKOMAT' => 'InPost Paczkomat 24/7', 'POCZTEX' => 'POCZTEX'],
            $this->createProvider()->getAll(),
        );
    }

    private function createProvider(): CarrierNameProvider
    {
        $configs = [];
        foreach (['INPOST_PACZKOMAT' => 'InPost Paczkomat 24/7', 'DPD' => 'DPD', 'POCZTEX' => ''] as $code => $name) {
            $config = $this->createStub(CarrierConfigInterface::class);
            $config->method('getCarrierCode')->willReturn($code);
            $config->method('getCarrierName')->willReturn($name);
            $configs[$code] = $config;
        }
        $all = $this->createStub(CarrierConfigProvider::class);
        $all->method('getAll')->willReturn($configs);
        $all->method('get')
            ->willReturnCallback(static fn(string $code): ?CarrierConfigInterface => $configs[$code] ?? null);

        return new CarrierNameProvider($all);
    }
}
