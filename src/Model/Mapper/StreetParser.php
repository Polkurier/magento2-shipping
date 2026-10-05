<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Mapper;

/**
 * Splits a Magento street into street, house number and flat number.
 *
 * Magento keeps the street as up to four free-form lines and never separates the house number,
 * which the API requires. Two conventions are understood: the number on a line of its own
 * ("Długa" / "12/3" or "Długa" / "12" / "3"), and the number at the end of the first line
 * ("Długa 12/3", "Długa 12 m. 3", "al. Jana Pawła II 5A lok. 7"). Before a flat said as such
 * ("m.", "lok."), a slash is the house's own double number: "Marszałkowska 84/92 m. 12".
 */
readonly class StreetParser
{
    private const string NUMBER = '(\d+[A-Za-z]?(?:-\d+[A-Za-z]?)?)';
    private const string FLAT_MARKER = '\s+(?:m\.?|lok\.?|lokal|mieszk\.?|apt\.?)\s*';
    private const string FLAT_SEPARATOR = '(?:\s*\/\s*|' . self::FLAT_MARKER . ')';
    // A house number and a flat said as such: the house may be a double number, "84/92".
    private const string MARKED_FLAT = '(\d+[A-Za-z]?(?:\s*[-\/]\s*\d+[A-Za-z]?)?)' . self::FLAT_MARKER . '(\w+)';

    /**
     * @param string[] $lines
     * @return array{street: string, house_number: ?string, flat_number: ?string}
     */
    public function parse(array $lines): array
    {
        $lines = \array_values(\array_filter(\array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
        $first = $lines[0] ?? '';

        if (isset($lines[1]) && \preg_match('/^' . self::MARKED_FLAT . '$/u', $lines[1], $m)) {
            return ['street' => $first, 'house_number' => self::compact($m[1]), 'flat_number' => $m[2]];
        }

        if (isset($lines[1]) && \preg_match('/^' . self::NUMBER . '(?:' . self::FLAT_SEPARATOR . '(\w+))?$/u', $lines[1], $m)) {
            return [
                'street' => $first,
                'house_number' => $m[1],
                'flat_number' => ($m[2] ?? '') !== '' ? $m[2] : (isset($lines[2]) ? $lines[2] : null),
            ];
        }

        if (\preg_match('/^(.*?\D)\s+' . self::MARKED_FLAT . '$/u', $first, $m)) {
            return ['street' => \trim($m[1]), 'house_number' => self::compact($m[2]), 'flat_number' => $m[3]];
        }

        if (\preg_match('/^(.*?\D)\s+' . self::NUMBER . '(?:' . self::FLAT_SEPARATOR . '(\w+))?$/u', $first, $m)) {
            return [
                'street' => \trim($m[1]),
                'house_number' => $m[2],
                'flat_number' => ($m[3] ?? '') !== '' ? $m[3] : null,
            ];
        }

        return ['street' => \implode(' ', $lines), 'house_number' => null, 'flat_number' => null];
    }

    private static function compact(string $number): string
    {
        return (string)\preg_replace('/\s+/', '', $number);
    }
}
