<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Magento\Framework\Locale\Format;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Sdk\PolkurierTimeZoneProvider;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;

/**
 * How the module writes numbers, money, times, sizes and places for the admin, in the admin's locale
 * and time zone.
 */
readonly class Formatter
{
    /**
     * What stands for a value there is none of.
     */
    public const string NONE = '—';

    public function __construct(
        private TimezoneInterface $timezone,
        private PriceCurrencyInterface $priceCurrency,
        private ResolverInterface $localeResolver,
        private Format $localeFormat,
    ) {}

    public function formatNumber(float $value): string
    {
        return (string)(new \NumberFormatter($this->localeResolver->getLocale(), \NumberFormatter::DECIMAL))
            ->format($value);
    }

    /**
     * An amount in złoty, the currency of everything Polkurier charges and collects.
     */
    /**
     * How formatMoney() writes an amount, for Magento's JS price formatter
     * (Magento_Catalog/js/price-utils formatPrice()).
     *
     * @return array<string, mixed>
     */
    public function getPriceFormat(): array
    {
        return $this->localeFormat->getPriceFormat(null, OrderAmountCalculator::CURRENCY);
    }

    public function formatMoney(float $amount): string
    {
        return $this->priceCurrency->format($amount, false, 2, null, OrderAmountCalculator::CURRENCY);
    }

    /**
     * A time the module stored, in UTC.
     */
    public function formatStoredTime(?string $value): string
    {
        return $value
            ? $this->timezone->formatDateTime($value, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT)
            : self::NONE;
    }

    /**
     * A time as Polkurier reported it, in Polish time, shown like the module's own times.
     */
    public function formatPolkurierTime(?string $value): string
    {
        if (!$value) {
            return self::NONE;
        }
        try {
            $time = (new \DateTimeImmutable($value, PolkurierTimeZoneProvider::getZone()))
                ->setTimezone(new \DateTimeZone('UTC'));
        } catch (\DateMalformedStringException) {
            return $value;
        }

        return $this->timezone->formatDateTime($time, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
    }

    public function formatSize(mixed $length, mixed $width, mixed $height): string
    {
        return (string)__('%1 × %2 × %3 cm', $length ?? '?', $width ?? '?', $height ?? '?');
    }

    public function formatWeight(float $kilograms): string
    {
        return (string)__('%1 kg', $this->formatNumber($kilograms));
    }

    /**
     * The postcode and town, and the country when it isn't Poland.
     */
    public function formatPlace(string $postcode, string $city, ?string $country): string
    {
        $place = \trim($postcode . ' ' . $city);

        return $country !== null && $country !== '' && $country !== Polkurier::HOME_COUNTRY
            ? \trim($place . ' (' . $country . ')')
            : $place;
    }
}
