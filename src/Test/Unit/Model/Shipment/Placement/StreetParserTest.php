<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Placement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Placement\StreetParser;

#[CoversClass(StreetParser::class)]
class StreetParserTest extends TestCase
{
    /**
     * @return array<string, array{0: string[], 1: array{street: string, house_number: ?string, flat_number: ?string}}>
     */
    public static function provideStreets(): array
    {
        return [
            'number at the end' => [['Długa 12'], ['street' => 'Długa', 'house_number' => '12', 'flat_number' => null]],
            'number and flat' => [['Długa 12/3'], ['street' => 'Długa', 'house_number' => '12', 'flat_number' => '3']],
            'flat after m.' => [
                ['ul. Krótka 5A m. 7'],
                ['street' => 'ul. Krótka', 'house_number' => '5A', 'flat_number' => '7'],
            ],
            'roman numeral in the name' => [
                ['al. Jana Pawła II 5 lok. 2'],
                ['street' => 'al. Jana Pawła II', 'house_number' => '5', 'flat_number' => '2'],
            ],
            'number on the second line' => [
                ['Długa', '12/3'],
                ['street' => 'Długa', 'house_number' => '12', 'flat_number' => '3'],
            ],
            'three lines' => [
                ['Długa', '12', '3'],
                ['street' => 'Długa', 'house_number' => '12', 'flat_number' => '3'],
            ],
            'no number' => [['Rynek'], ['street' => 'Rynek', 'house_number' => null, 'flat_number' => null]],
            'range' => [['Polna 10-12'], ['street' => 'Polna', 'house_number' => '10-12', 'flat_number' => null]],
            'double number and flat' => [
                ['Marszałkowska 84/92 m. 12'],
                ['street' => 'Marszałkowska', 'house_number' => '84/92', 'flat_number' => '12'],
            ],
            'double number and flat on the second line' => [
                ['Marszałkowska', '84/92 lok. 12'],
                ['street' => 'Marszałkowska', 'house_number' => '84/92', 'flat_number' => '12'],
            ],
            'flat after lokal' => [
                ['Długa 12 lokal 3'],
                ['street' => 'Długa', 'house_number' => '12', 'flat_number' => '3'],
            ],
        ];
    }

    /**
     * @param string[] $lines
     * @param array{street: string, house_number: ?string, flat_number: ?string} $expected
     */
    #[DataProvider('provideStreets')]
    public function testParses(array $lines, array $expected): void
    {
        self::assertSame($expected, (new StreetParser())->parse($lines));
    }
}
