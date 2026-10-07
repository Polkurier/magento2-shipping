<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config\Source\ShippingCarrier;

#[CoversClass(ShippingCarrier::class)]
class ShippingCarrierTest extends TestCase
{
    public function testEveryCarrierByItsTitleWithItsCode(): void
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn([
            'polkurier' => [
                'model' => 'Polkurier\Shipping\Model\Carrier\Polkurier',
                'title' => 'Polkurier',
                'active' => '1',
            ],
            'flatrate' => [
                'model' => 'Magento\OfflineShipping\Model\Carrier\Flatrate',
                'title' => 'Flat Rate',
                'active' => '0',
            ],
            'untitled' => ['model' => 'Some\Carrier'],
            'leftover' => ['title' => 'A setting without a carrier'],
        ]);

        self::assertSame(
            ['flatrate' => 'Flat Rate (flatrate)', 'polkurier' => 'Polkurier', 'untitled' => 'untitled'],
            (new ShippingCarrier($config))->toArray(),
        );
    }
}
