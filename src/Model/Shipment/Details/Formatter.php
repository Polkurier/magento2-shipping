<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Details;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Shipment\PlnAmounts;

/**
 * How the shipment details page writes numbers, money, times, sizes and places, in the admin's
 * locale and time zone.
 */
class Formatter
{
    /**
     * The time zone of the times Polkurier reports: statuses, deliveries, carrier scans.
     */
    public const string POLKURIER_TIME_ZONE = 'Europe/Warsaw';

    private const string NONE = '—';

    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ResolverInterface $localeResolver,
    ) {}

    public function number(float $value): string
    {
        return (string)(new \NumberFormatter($this->localeResolver->getLocale(), \NumberFormatter::DECIMAL))->format($value);
    }

    /**
     * An amount in złoty, the currency of everything Polkurier charges and collects.
     */
    public function money(float $amount): string
    {
        return $this->priceCurrency->format($amount, false, 2, null, PlnAmounts::CURRENCY);
    }

    /**
     * A time the module stored, in UTC.
     */
    public function storedTime(?string $value): string
    {
        return $value ? $this->timezone->formatDateTime($value, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT) : self::NONE;
    }

    /**
     * A time as Polkurier reported it, in Polish time, shown like the module's own times.
     */
    public function polkurierTime(?string $value): string
    {
        if (!$value) {
            return self::NONE;
        }
        try {
            $time = (new \DateTimeImmutable($value, new \DateTimeZone(self::POLKURIER_TIME_ZONE)))
                ->setTimezone(new \DateTimeZone('UTC'));
        } catch (\DateMalformedStringException) {
            return $value;
        }

        return $this->timezone->formatDateTime($time, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
    }

    public function size(mixed $length, mixed $width, mixed $height): string
    {
        return (string)\__('%1 × %2 × %3 cm', $length ?? '?', $width ?? '?', $height ?? '?');
    }

    public function weight(float $kilograms): string
    {
        return (string)\__('%1 kg', $this->number($kilograms));
    }

    /**
     * The postcode and town, and the country when it isn't Poland.
     */
    public function place(string $postcode, string $city, ?string $country): string
    {
        $place = \trim($postcode . ' ' . $city);

        return $country !== null && $country !== '' && $country !== Polkurier::HOME_COUNTRY
            ? \trim($place . ' (' . $country . ')')
            : $place;
    }

    /**
     * @param array<int, array<int, mixed>|null> $rows [label, value, url?]; null rows are left out
     * @return array<int, array<string, string>>
     */
    public function rows(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if ($row === null) {
                continue;
            }
            $value = (string)($row[1] ?? '');
            $result[] = [
                'label' => (string)$row[0],
                'value' => $value !== '' ? $value : self::NONE,
                'url' => (string)($row[2] ?? ''),
            ];
        }

        return $result;
    }
}
