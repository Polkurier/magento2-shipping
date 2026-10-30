<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Exception\PlacementUncertainException;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Webservice\Api\Service\OrdersServiceInterface;
use Polkurier\Webservice\Enum\Http\ResponseStatusEnum;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\WebserviceException;
use Polkurier\Webservice\Http\Response\ErrorMessage;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Response\Order\OrderStatusMessage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(ShipmentGateway::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentGatewayTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function connectionFailures(): array
    {
        return [
            'no connection' => [
                'cURL error 7: Failed to connect to api.polkurier.pl port 443 after 0 ms: Could not connect to server',
                false,
            ],
            'no host' => ['cURL error 6: Could not resolve host: api.polkurier.pl', false],
            'no TLS handshake' => ['cURL error 35: OpenSSL SSL_connect: Connection reset by peer', false],
            'timed out connecting' => ['cURL error 28: Connection timed out after 5001 milliseconds', false],
            'timed out waiting for the answer' => [
                'cURL error 28: Operation timed out after 5002 milliseconds with 0 bytes received',
                true,
            ],
            'connection dropped mid-answer' => ['cURL error 56: Recv failure: Connection reset by peer', true],
        ];
    }

    #[DataProvider('connectionFailures')]
    public function testOnlyARequestThatMayHaveArrivedLeavesThePlacingUncertain(string $failure, bool $uncertain): void
    {
        $orders = $this->createStub(OrdersServiceInterface::class);
        $orders->method('createOrder')->willThrowException(new ConnectionException($failure));
        $client = $this->createStub(PolkurierClient::class);
        $client->method('orders')->willReturn($orders);

        try {
            (new ShipmentGateway($client, new ErrorMessageResolver()))
                ->placeOrder($this->createStub(CreateOrderMessage::class));
            self::fail('The failure is reported.');
        } catch (LocalizedException $e) {
            self::assertSame($uncertain, $e instanceof PlacementUncertainException);
        }
    }

    /**
     * @return array<string, array{\Closure(self): (OrderStatusMessage|\Throwable), ?bool}>
     */
    public static function statusAnswers(): array
    {
        $refusal = static fn(string $reason): \Closure => static fn(self $test): \Throwable => new WebserviceException(
            new ErrorMessage(ResponseStatusEnum::Error, $reason),
            $test->createStub(RequestInterface::class),
            $test->createStub(ResponseInterface::class),
        );

        return [
            'a status: a live order' => [
                static fn(self $test): OrderStatusMessage => $test->createStub(OrderStatusMessage::class),
                false,
            ],
            'refused as cancelled' => [$refusal('Zamówienie anulowane'), true],
            'refused otherwise' => [$refusal('Brak zamówienia 24680-27'), null],
            'not reached' => [
                static fn(): \Throwable => new ConnectionException('cURL error 7: Failed to connect'),
                null,
            ],
        ];
    }

    /**
     * @param \Closure(self): (OrderStatusMessage|\Throwable) $answer
     */
    #[DataProvider('statusAnswers')]
    public function testGetStatusTellsACancelledOrderFromALiveOne(\Closure $answer, ?bool $cancelled): void
    {
        $orders = $this->createStub(OrdersServiceInterface::class);
        $result = $answer($this);
        if ($result instanceof \Throwable) {
            $orders->method('getStatus')->willThrowException($result);
        } else {
            $orders->method('getStatus')->willReturn($result);
        }
        $client = $this->createStub(PolkurierClient::class);
        $client->method('orders')->willReturn($orders);

        self::assertSame($cancelled, (new ShipmentGateway($client, new ErrorMessageResolver()))->isCancelled('24680-27'));
    }
}
