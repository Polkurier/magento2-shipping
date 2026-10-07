<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
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
use Polkurier\Webservice\Exception\ConnectionException;
use Psr\Log\LoggerInterface;

#[CoversClass(Token::class)]
#[AllowMockObjectsWithoutExpectations]
class TokenTest extends TestCase
{
    public function testACheckoutPageIsGivenTheTokenAndWhenItRunsOut(): void
    {
        $tokens = $this->createStub(MapTokenProvider::class);
        $tokens->method('getToken')->willReturn('eyJhbGciOiJIUzI1NiJ9.map');
        $tokens->method('getExpiry')->willReturn(1791460800);

        $answer = $this->answer($tokens, $this->createStub(LoggerInterface::class));

        self::assertSame('{"token":"eyJhbGciOiJIUzI1NiJ9.map","expires":1791460800}', $answer['body']);
        self::assertSame(
            'no-store',
            $answer['headers']['Cache-Control'] ?? null,
            'Never cached: each page asks for itself.',
        );
    }

    public function testATokenThatCantBeHadIsAnEmptyOneAndLogged(): void
    {
        $tokens = $this->createStub(MapTokenProvider::class);
        $tokens->method('getToken')->willThrowException(new ConnectionException('Polkurier could not be reached.'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Pickup point map token unavailable', self::arrayHasKey('exception'));

        $answer = $this->answer($tokens, $logger);

        self::assertSame('{"token":"","expires":0}', $answer['body'], 'Nothing of the failure reaches the shopper.');
        self::assertSame('no-store', $answer['headers']['Cache-Control'] ?? null);
    }

    /**
     * The answer, as it reaches the wire.
     *
     * @return array{headers: array<string, string>, body: string}
     */
    private function answer(MapTokenProvider $tokens, LoggerInterface $logger): array
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
        (new Token($results, $tokens, $logger))->execute()->renderResult($response);

        return $answer;
    }
}
