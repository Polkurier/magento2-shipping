<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Map;

use Magento\Framework\App\Response\HttpInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Translate\InlineInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Controller\Map\Token;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;

#[CoversClass(Token::class)]
#[AllowMockObjectsWithoutExpectations]
class TokenTest extends TestCase
{
    public function testACheckoutPageIsGivenTheTokenAndWhenItRunsOut(): void
    {
        $tokens = $this->createStub(MapTokenProvider::class);
        $tokens->method('getTokenWithExpiry')
            ->willReturn(['token' => 'eyJhbGciOiJIUzI1NiJ9.map', 'expires' => 1791460800]);

        $answer = $this->answer($tokens);

        self::assertSame('{"token":"eyJhbGciOiJIUzI1NiJ9.map","expires":1791460800}', $answer['body']);
        self::assertSame(
            'no-store',
            $answer['headers']['Cache-Control'] ?? null,
            'Never cached: each page asks for itself.',
        );
    }

    /**
     * The answer, as it reaches the wire.
     *
     * @return array{headers: array<string, string>, body: string}
     */
    private function answer(MapTokenProvider $tokens): array
    {
        $results = $this->createStub(JsonFactory::class);
        $results->method('create')
            ->willReturnCallback(fn(): Json => new Json(
                $this->createStub(InlineInterface::class),
                new JsonSerializer(),
            ));

        $answer = ['headers' => [], 'body' => ''];
        $response = $this->createStub(HttpInterface::class);
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
        (new Token($results, $tokens))->execute()->renderResult($response);

        return $answer;
    }
}
