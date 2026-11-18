<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\PickupService;

#[CoversClass(PickupService::class)]
class PickupWindowTest extends TestCase
{
    public function testAWindowIsInPolishTime(): void
    {
        $window = PickupService::buildWindow('2026-10-08', '09:00', '15:30');

        self::assertSame('2026-10-08 09:00 Europe/Warsaw', $window['from']->format('Y-m-d H:i e'));
        self::assertSame('2026-10-08 15:30', $window['to']->format('Y-m-d H:i'));
        self::assertSame('2026-10-08 00:00', $window['date']->format('Y-m-d H:i'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function provideWrong(): array
    {
        return [
            'no such month' => ['2026-13-08', '09:00', '15:00'],
            'no such day' => ['2026-02-30', '09:00', '15:00'],
            'no such hour' => ['2026-10-08', '25:00', '26:00'],
            'no such minute' => ['2026-10-08', '09:99', '15:00'],
            'ends before it starts' => ['2026-10-08', '15:00', '09:00'],
            'ends as it starts' => ['2026-10-08', '09:00', '09:00'],
            'not a date' => ['8.10.2026', '09:00', '15:00'],
            'empty' => ['', '', ''],
        ];
    }

    #[DataProvider('provideWrong')]
    public function testAWindowThatIsNoneIsRefused(string $date, string $from, string $to): void
    {
        $this->expectException(LocalizedException::class);

        PickupService::buildWindow($date, $from, $to);
    }
}
