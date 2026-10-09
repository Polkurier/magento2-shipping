<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Logger;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Logger\TokenRedactingProcessor;
use Polkurier\Webservice\Enum\Http\ApiMethodEnum;
use Polkurier\Webservice\Http\Request\AuthorizationMessage;
use Polkurier\Webservice\Http\Request\RequestMessage;
use Polkurier\Webservice\Request\BankAccount\SaveMessage;
use Polkurier\Webservice\Request\MoneyBox\CreateMoneyBoxPayoutMessage;
use Polkurier\Webservice\Request\Order\GetStatusMessage;
use Polkurier\Webservice\Request\TwoFactorToken\VerifyMessage;
use Polkurier\Webservice\Serializer\SerializerProvider;

#[CoversClass(TokenRedactingProcessor::class)]
class TokenRedactingProcessorTest extends TestCase
{
    public function testMasksTheTokenInTheMessageAndTheRequest(): void
    {
        $body = '{"apimethod":"create_order","authorization":{"login":"12345","token":"s3cr3t-\"token\""},'
            . '"data":{"courier":"DPD"}}';
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

    public function testMonolog2RecordsOfMagento247AreMaskedAsWell(): void
    {
        $body = '{"authorization":{"login":"12345","token":"s3cr3t"}}';
        $record = [
            'message' => 'payload: ' . $body,
            'context' => ['request' => new Request('POST', '/', [], $body), 'pin' => '2342'],
            'level' => 400,
            'channel' => 'polkurier',
            'extra' => [],
        ];

        $redacted = (new TokenRedactingProcessor())($record);

        self::assertIsArray($redacted, 'The record comes back as it came: an array.');
        self::assertSame('payload: {"authorization":{"login":"12345","token":"***"}}', $redacted['message']);
        self::assertSame(
            ['request' => '{"authorization":{"login":"12345","token":"***"}}', 'pin' => '***'],
            $redacted['context'],
        );
        self::assertSame(400, $redacted['level']);
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
            $serializer->serialize(
                new SaveMessage(
                    'Main',
                    'PL61109010140000071219812874',
                    twoFactorToken: 'tok-2fa',
                    twoFactorCode: '481516',
                    accessPin: '2342',
                ),
                'json',
            ),
            $serializer->serialize(
                new CreateMoneyBoxPayoutMessage(
                    ['18015-1'],
                    securityPin: '9876',
                    twoFactorToken: 'tok-2fa',
                    twoFactorCode: '481516',
                ),
                'json',
            ),
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
        self::assertStringContainsString(
            'PL61109010140000071219812874',
            TokenRedactingProcessor::redact($bodies[0]),
            'Only the secrets go.',
        );
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

    public function testMasksTheTokenOfTheSdksOwnRequestEnvelope(): void
    {
        $body = (new SerializerProvider())->getSerializer()->serialize(
            new RequestMessage(
                ApiMethodEnum::GetStatus,
                new AuthorizationMessage('s3cr3t-api-token', '12345'),
                new GetStatusMessage('711400-4', tracking: true),
            ),
            'json',
        );
        self::assertStringContainsString('s3cr3t-api-token', $body, 'The SDK sends the token.');
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'polkurier',
            Level::Info,
            'Request method: "get_status", payload: "' . $body . '"',
            [
                'request' => new Request(
                    'POST',
                    'https://api.polkurier.pl/',
                    ['Content-Type' => 'application/json'],
                    $body,
                ),
            ],
        );

        $redacted = (new TokenRedactingProcessor())($record);

        self::assertStringNotContainsString('s3cr3t-api-token', $redacted->message);
        self::assertStringContainsString('"authorization":{"login":"12345","token":"***"}', $redacted->message);
        self::assertIsString($redacted->context['request']);
        self::assertStringNotContainsString('s3cr3t-api-token', $redacted->context['request']);
        self::assertStringContainsString(
            '"apimethod":"get_status"',
            $redacted->context['request'],
            'Only the secrets go.',
        );
    }

    public function testMasksTheSecretsOfAnAnswerInTheContext(): void
    {
        $body = '{"status":"success","response":{"verification_id":"0a1b2c3d4e5f6071","type":"online",'
            . '"status":"NEW","access_token":"a1b2c3d4e5f60718293a4b5c6d7e8f90"}}';
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'polkurier',
            Level::Info,
            'Response from method: "register_account_verification", response: "' . $body . '"',
            [
                'request' => new Request('POST', '/', [], '{}'),
                'response' => new Response(200, ['Content-Type' => 'application/json'], $body),
            ],
        );

        $redacted = (new TokenRedactingProcessor())($record);

        self::assertStringNotContainsString('a1b2c3d4e5f60718293a4b5c6d7e8f90', $redacted->message);
        self::assertIsString($redacted->context['response'], 'The response gives way to its body.');
        self::assertSame(
            '{"status":"success","response":{"verification_id":"0a1b2c3d4e5f6071","type":"online",'
            . '"status":"NEW","access_token":"***"}}',
            $redacted->context['response'],
        );
    }

    public function testAnAnswerInAMonolog2RecordIsMaskedAndCanStillBeRead(): void
    {
        $body = '{"status":"success","response":{"id":12345,"phone_pin":"4455","display_name":"Jan Kowalski",'
            . '"email":"jan@example.com","is_active":true}}';
        $response = new Response(200, ['Content-Type' => 'application/json'], $body);
        $record = [
            'message' => 'Response from method: "get_account_info"',
            'context' => ['response' => $response],
            'level' => 200,
            'channel' => 'polkurier',
            'extra' => [],
        ];

        $redacted = (new TokenRedactingProcessor())($record);

        self::assertIsArray($redacted);
        self::assertSame(
            [
                'response' => '{"status":"success","response":{"id":12345,"phone_pin":"***",'
                    . '"display_name":"Jan Kowalski","email":"jan@example.com","is_active":true}}',
            ],
            $redacted['context'],
        );
        self::assertSame(
            $body,
            $response->getBody()->getContents(),
            'Logging leaves the stream to be read again from the start.',
        );
    }
}
