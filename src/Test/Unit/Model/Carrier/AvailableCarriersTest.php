<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Carrier\AvailableCarriers;
use Polkurier\Shipping\Model\Config;

#[CoversClass(AvailableCarriers::class)]
#[AllowMockObjectsWithoutExpectations]
class AvailableCarriersTest extends TestCase
{
    public function testTheSelectedCarriersAreKept(): void
    {
        $available = $this->available(['DPD', 'INPOST_PACZKOMAT']);

        self::assertSame(['DPD' => 1, 'INPOST_PACZKOMAT' => 3], $available->filter(['DPD' => 1, 'DHL' => 2, 'INPOST_PACZKOMAT' => 3]));
        self::assertTrue($available->isAvailable('DPD'));
        self::assertFalse($available->isAvailable('DHL'));
        self::assertSame(['DPD', 'INPOST_PACZKOMAT'], $available->getCodes());
    }

    public function testNothingSelectedKeepsEveryCarrier(): void
    {
        $available = $this->available([]);

        self::assertSame(['DPD' => 1, 'DHL' => 2], $available->filter(['DPD' => 1, 'DHL' => 2]));
        self::assertTrue($available->isAvailable('DHL'));
        self::assertNull($available->getCodes());
    }

    /**
     * @param string[] $codes
     */
    private function available(array $codes): AvailableCarriers
    {
        $config = $this->createStub(Config::class);
        $config->method('getAvailableCarriers')->willReturn($codes);

        return new AvailableCarriers($config);
    }
}
