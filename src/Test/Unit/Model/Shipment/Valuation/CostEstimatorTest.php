<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Valuation;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Placement\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Shipment\Valuation\CostEstimator;
use Polkurier\Webservice\Api\Service\OrdersServiceInterface;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(CostEstimator::class)]
#[AllowMockObjectsWithoutExpectations]
class CostEstimatorTest extends TestCase
{
    public function testEveryCarrierThatCanTakeTheShipmentIsPriced(): void
    {
        $estimator = $this->estimator([
            ['servicecode' => 'DPD', 'netprice' => 17.87, 'grossprice' => 21.98, 'available' => true],
            ['servicecode' => 'INPOST_PACZKOMAT', 'netprice' => 12.06, 'grossprice' => 14.83, 'available' => true],
            ['servicecode' => 'UPS', 'netprice' => 120.0, 'grossprice' => 147.6, 'available' => false],
            ['servicecode' => 'GLS', 'available' => true],
        ]);

        $quotes = $estimator->quoteAll((new Draft())->setCarrierCode('DPD'), 1);

        self::assertSame([
            'DPD' => ['net' => 17.87, 'gross' => 21.98],
            'INPOST_PACZKOMAT' => ['net' => 12.06, 'gross' => 14.83],
        ], $quotes, 'Unavailable and unpriced carriers are left out.');
    }

    public function testASingleQuoteIsTheChosenCarriers(): void
    {
        $estimator = $this->estimator(['netprice' => 9.0, 'grossprice' => 11.07]);

        self::assertSame(
            ['DPD' => ['net' => 9.0, 'gross' => 11.07]],
            $estimator->quoteAll((new Draft())->setCarrierCode('DPD'), 1),
        );
    }

    public function testPolkuriersOwnAnswerIsRead(): void
    {
        $quotes = $this->estimator(self::recorded('order_valuation_v2.json'))
            ->quoteAll((new Draft())->setCarrierCode('DPD'), 1);

        self::assertSame(['net' => 48.84, 'gross' => 60.07], $quotes['DPD']);
        self::assertSame(['AMBRO', 'INPOST', 'UPS', 'DPD', 'DHL', 'FEDEX'], \array_keys($quotes));
    }

    public function testTheShapeTheSdkDocumentsIsReadToo(): void
    {
        $quotes = $this->estimator(self::recorded('order_valuation_v2_documented.json'))
            ->quoteAll((new Draft())->setCarrierCode('DPD'), 1);

        self::assertSame(['UPS' => ['net' => 19.5, 'gross' => 23.99]], $quotes);
    }

    public function testAnAnswerThatCantBeReadPricesNothingAndIsLogged(): void
    {
        $logged = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(
            function (string|\Stringable $message, array $context) use (&$logged): void {
                $logged[] = $context;
            },
        );

        self::assertSame(
            [],
            $this->estimator(['price' => 'soon', 'UPS' => 'n/a'], logger: $logger)
                ->quoteAll((new Draft())->setCarrierCode('DPD'), 1),
        );
        self::assertCount(1, $logged);
    }

    public function testAFailureIsReportedForTheMerchant(): void
    {
        $orders = $this->createStub(OrdersServiceInterface::class);
        $orders->method('orderValuation')->willThrowException(new ConnectionException('timeout'));

        $this->expectException(LocalizedException::class);
        $this->estimator([], $orders)->quoteAll((new Draft())->setCarrierCode('DPD'), 1);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function recorded(string $name): array
    {
        return (array)\json_decode((string)\file_get_contents(__DIR__ . '/../../../_files/' . $name), true);
    }

    /**
     * @param array<int|string, mixed> $valuation
     */
    private function estimator(
        array $valuation,
        ?OrdersServiceInterface $orders = null,
        ?LoggerInterface $logger = null,
    ): CostEstimator {
        $message = $this->createStub(CreateOrderMessage::class);
        $message->method('getCourier')->willReturn('DPD');
        $mapper = $this->createStub(CreateOrderMessageMapper::class);
        $mapper->method('map')->willReturn($message);
        if ($orders === null) {
            $orders = $this->createStub(OrdersServiceInterface::class);
            $orders->method('orderValuation')->willReturn($valuation);
        }
        $client = $this->createStub(PolkurierClient::class);
        $client->method('orders')->willReturn($orders);

        return new CostEstimator(
            $mapper,
            new ShipmentGateway($client, new ErrorMessageResolver()),
            $logger ?? new NullLogger(),
        );
    }
}
