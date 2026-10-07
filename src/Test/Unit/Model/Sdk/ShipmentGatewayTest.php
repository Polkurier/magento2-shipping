<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
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
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;

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
}
