<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Logger;

use GuzzleHttp\Psr7\Request;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Logger\TokenRedactingProcessor;

#[CoversClass(TokenRedactingProcessor::class)]
class TokenRedactingProcessorTest extends TestCase
{
    public function testMasksTheTokenInTheMessageAndTheRequest(): void
    {
        $body = '{"apimethod":"create_order","authorization":{"login":"12345","token":"s3cr3t-\"token\""},"data":{"courier":"DPD"}}';
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'polkurier',
            Level::Error,
            'Request method: "create_order", payload: "' . $body . '"',
            ['request' => new Request('POST', '/', [], $body), 'payload' => 'kept'],
        );

        $redacted = (new TokenRedactingProcessor())($record);

        self::assertStringNotContainsString('s3cr3t', $redacted->message);
        self::assertStringContainsString('"token":"***"', $redacted->message);
        self::assertStringContainsString('"login":"12345"', $redacted->message);
        self::assertIsString($redacted->context['request']);
        self::assertStringNotContainsString('s3cr3t', $redacted->context['request']);
        self::assertSame('kept', $redacted->context['payload']);
    }

    public function testMasksPinsAndCodes(): void
    {
        self::assertSame(
            '{"security_pin":"***","two_factor_code":"***","access_pin":"***"}',
            TokenRedactingProcessor::redact('{"security_pin":"1234","two_factor_code":123456,"access_pin":"0000"}'),
        );
    }
}
