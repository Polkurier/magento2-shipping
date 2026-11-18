<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Webhook\Shipment;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\HttpInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Controller\Webhook\Shipment\Update;
use Polkurier\Shipping\Model\Shipment\Status\StatusWebhookHandler;

/**
 * What Polkurier's status webhook is answered: Polkurier takes an event as delivered only on a
 * body of exactly "OK".
 */
#[CoversClass(Update::class)]
#[AllowMockObjectsWithoutExpectations]
class UpdateTest extends TestCase
{
    public function testAReceivedEventIsAnsweredWithExactlyOk(): void
    {
        $answer = $this->answer(StatusWebhookHandler::RECEIVED);

        self::assertSame('OK', $answer['body']);
        self::assertNull($answer['code'], 'The default 200.');
        self::assertSame(['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store'], $answer['headers']);
    }

    public function testARefusedEventIsForbidden(): void
    {
        $answer = $this->answer(StatusWebhookHandler::REFUSED);

        self::assertSame(403, $answer['code']);
        self::assertSame('Forbidden', $answer['body']);
    }

    public function testAnEventThatFailedIsToBeSentAgain(): void
    {
        $answer = $this->answer(StatusWebhookHandler::FAILED);

        self::assertSame(503, $answer['code']);
        self::assertNotSame('OK', $answer['body'], 'Anything but "OK" has Polkurier send it again.');
    }

    public function testTheBodyIsHandedOnAsItWasPosted(): void
    {
        // Its signature covers the raw bytes: nothing may be parsed or re-encoded on the way.
        $body = \http_build_query([
            'type' => 'UPDATED',
            'data' => ['number' => '24680-27', 'status' => 'Oczekuje na odbiór', 'status_code' => 'OC'],
        ]);
        $body .= '&hash=' . \hash_hmac('sha256', $body, 'panel-secret');
        $webhook = $this->createMock(StatusWebhookHandler::class);
        $webhook->expects(self::once())->method('receive')->with($body)->willReturn(StatusWebhookHandler::RECEIVED);

        $this->createUpdate($webhook, $body)->execute();
    }

    /**
     * The answer to an event the webhook found $outcome, as it reaches the wire.
     *
     * @return array{code: int|null, headers: array<string, string>, body: string}
     */
    private function answer(string $outcome): array
    {
        $webhook = $this->createStub(StatusWebhookHandler::class);
        $webhook->method('receive')->willReturn($outcome);

        $answer = ['code' => null, 'headers' => [], 'body' => ''];
        $response = $this->createStub(HttpInterface::class);
        $response->method('setHttpResponseCode')
            ->willReturnCallback(function (int $code) use (&$answer, $response): HttpInterface {
                $answer['code'] = $code;

                return $response;
            });
        $response->method('setHeader')
            ->willReturnCallback(function (string $name, string $value) use (&$answer, $response): HttpInterface {
                $answer['headers'][$name] = $value;

                return $response;
            });
        $response->method('setBody')
            ->willReturnCallback(function (string $body) use (&$answer, $response): HttpInterface {
                $answer['body'] = $body;

                return $response;
            });
        $this->createUpdate($webhook, 'type=UPDATED&hash=0')->execute()->renderResult($response);

        return $answer;
    }

    private function createUpdate(StatusWebhookHandler $webhook, string $body): Update
    {
        $request = $this->createStub(Http::class);
        $request->method('getContent')->willReturn($body);
        $results = $this->createStub(RawFactory::class);
        $results->method('create')->willReturnCallback(static fn(): Raw => new Raw());

        return new Update($request, $results, $webhook);
    }
}
