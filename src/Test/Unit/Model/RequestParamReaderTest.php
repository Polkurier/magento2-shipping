<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model;

use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\RequestParamReader;

#[CoversClass(RequestParamReader::class)]
class RequestParamReaderTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function provideValues(): array
    {
        return [
            'text' => ['ZPL', 'ZPL'],
            'number' => [12, '12'],
            'missing' => [null, 'fallback'],
            'list' => [['ZPL'], ''],
        ];
    }

    #[DataProvider('provideValues')]
    public function testAParameterReadsAsTextAndAListAsEmpty(mixed $param, string $expected): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn(string $name, mixed $default = null): mixed => $param ?? $default,
        );

        self::assertSame($expected, RequestParamReader::getString($request, 'format', 'fallback'));
    }
}
