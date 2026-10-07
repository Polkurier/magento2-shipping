<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Details;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;

#[CoversClass(Formatter::class)]
#[AllowMockObjectsWithoutExpectations]
class FormatterTest extends TestCase
{
    public function testPolkurierTimesAreShownInTheAdminsTimeZoneLikeTheModulesOwn(): void
    {
        $format = $this->formatter();

        // 14:30 in Warsaw in September is 12:30 UTC, the moment the admin's time zone is applied to.
        self::assertSame('2026-09-29T12:30:00+00:00', $format->polkurierTime('2026-09-29 14:30:00'));
        self::assertSame('2026-09-29T12:30:00+00:00', $format->storedTime('2026-09-29 12:30:00'));
        self::assertSame('—', $format->polkurierTime(null));
        self::assertSame('not a date', $format->polkurierTime('not a date'));
    }

    public function testTheCountryIsNamedOnlyAbroad(): void
    {
        $format = $this->formatter();

        self::assertSame('00-590 Warszawa', $format->place('00-590', 'Warszawa', 'PL'));
        self::assertSame('10115 Berlin (DE)', $format->place('10115', 'Berlin', 'DE'));
    }

    public function testSizesAndWeightsKeepTheirUnits(): void
    {
        $format = $this->formatter();

        self::assertSame('40 × 30 × ? cm', $format->size(40, 30, null));
        self::assertSame('1.5 kg', $format->weight(1.5));
    }

    private function formatter(): Formatter
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnCallback(
            static fn(mixed $date): string => ($date instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($date)
                : new \DateTimeImmutable((string)$date, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format(\DATE_ATOM),
        );
        $locale = $this->createStub(ResolverInterface::class);
        $locale->method('getLocale')->willReturn('en_US');

        return new Formatter($timezone, $this->createStub(PriceCurrencyInterface::class), $locale);
    }
}
