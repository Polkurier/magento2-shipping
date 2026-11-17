<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\PickupPoint;

use Magento\Backend\Model\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\AdminMapConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;

#[CoversClass(AdminMapConfigProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class AdminMapConfigProviderTest extends TestCase
{
    public function testAPageIsGivenTheTokenWhenAskedAndTheWayToAFreshOneAlways(): void
    {
        $settings = $this->createStub(ConfigProvider::class);
        $settings->method('getMapUrl')->willReturn('https://maps.example');
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnArgument(0);
        $tokens = $this->createStub(MapTokenProvider::class);
        $tokens->method('getTokenWithExpiry')->willReturn(['token' => 'map-token', 'expires' => 1790014400]);
        $maps = new AdminMapConfigProvider($settings, $url, $tokens);

        self::assertSame(
            ['baseUrl' => 'https://maps.example/', 'tokenUrl' => AdminMapConfigProvider::TOKEN_ROUTE],
            $maps->getMapConfig(false),
        );
        self::assertSame(
            [
                'baseUrl' => 'https://maps.example/',
                'tokenUrl' => AdminMapConfigProvider::TOKEN_ROUTE,
                'token' => 'map-token',
                'tokenExpires' => 1790014400,
            ],
            $maps->getMapConfig(true),
        );
    }
}
