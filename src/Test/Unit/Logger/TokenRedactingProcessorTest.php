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
use Polkurier\Webservice\Request\BankAccount\SaveMessage;
use Polkurier\Webservice\Request\MoneyBox\CreateMoneyBoxPayoutMessage;
use Polkurier\Webservice\Request\TwoFactorToken\VerifyMessage;
use Polkurier\Webservice\Serializer\SerializerProvider;

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

    public function testMasksTheSecretsOfRealConfirmations(): void
    {
        $serializer = (new SerializerProvider())->getSerializer();
        $bodies = [
            $serializer->serialize(new SaveMessage('Main', 'PL61109010140000071219812874', twoFactorToken: 'tok-2fa', twoFactorCode: '481516', accessPin: '2342'), 'json'),
            $serializer->serialize(new CreateMoneyBoxPayoutMessage(['18015-1'], securityPin: '9876', twoFactorToken: 'tok-2fa', twoFactorCode: '481516'), 'json'),
            $serializer->serialize(new VerifyMessage('tok-2fa', '481516'), 'json'),
            '{"status":"success","response":{"access_token":"acc-tok","phone_pin":"4455"}}',
        ];

        foreach ($bodies as $body) {
            self::assertMatchesRegularExpression('/tok-2fa|acc-tok/', $body, 'The SDK sends the secret.');
            $redacted = TokenRedactingProcessor::redact($body);
            foreach (['tok-2fa', '481516', '2342', '9876', 'acc-tok', '4455'] as $secret) {
                self::assertStringNotContainsString($secret, $redacted, $body);
            }
        }
        self::assertStringContainsString('PL61109010140000071219812874', TokenRedactingProcessor::redact($bodies[0]), 'Only the secrets go.');
    }

    public function testMasksSecretsNestedInTheContext(): void
    {
        $body = '{"authorization":{"token":"s3cr3t"}}';
        $record = new LogRecord(new \DateTimeImmutable(), 'polkurier', Level::Info, 'Sent', [
            'exchange' => ['request' => new Request('POST', '/', [], $body), 'body' => $body],
            'confirmation' => ['two_factor_token' => 'tok-2fa', 'pin' => 1234, 'website' => 1],
        ]);

        $context = (new TokenRedactingProcessor())($record)->context;

        self::assertSame('{"authorization":{"token":"***"}}', $context['exchange']['request']);
        self::assertSame('{"authorization":{"token":"***"}}', $context['exchange']['body']);
        self::assertSame(['two_factor_token' => '***', 'pin' => '***', 'website' => 1], $context['confirmation']);
    }
}
