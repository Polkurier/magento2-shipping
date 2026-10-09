<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Carrier\AvailableCarrierFilter;
use Polkurier\Shipping\Model\Config\ConfigProvider;

#[CoversClass(AvailableCarrierFilter::class)]
#[AllowMockObjectsWithoutExpectations]
class AvailableCarrierFilterTest extends TestCase
{
    public function testTheSelectedCarriersAreKept(): void
    {
        $available = $this->available(['DPD', 'INPOST_PACZKOMAT']);

        self::assertSame(
            ['DPD' => 1, 'INPOST_PACZKOMAT' => 3],
            $available->filter(['DPD' => 1, 'DHL' => 2, 'INPOST_PACZKOMAT' => 3]),
        );
        self::assertSame(['DPD', 'INPOST_PACZKOMAT'], $available->getCodes());
    }

    public function testNothingSelectedKeepsEveryCarrier(): void
    {
        $available = $this->available([]);

        self::assertSame(['DPD' => 1, 'DHL' => 2], $available->filter(['DPD' => 1, 'DHL' => 2]));
        self::assertNull($available->getCodes());
    }

    /**
     * @param string[] $codes
     */
    private function available(array $codes): AvailableCarrierFilter
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getAvailableCarriers')->willReturn($codes);

        return new AvailableCarrierFilter($config);
    }
}
