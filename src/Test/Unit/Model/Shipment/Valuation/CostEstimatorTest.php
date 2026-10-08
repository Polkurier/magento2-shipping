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
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Placement\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Shipment\Valuation\CostEstimator;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Request\Order\OrderAddressMessage;
use Polkurier\Webservice\Request\Order\PackMessage;
use Polkurier\Webservice\Response\Order\ValuationMessage;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(CostEstimator::class)]
#[AllowMockObjectsWithoutExpectations]
class CostEstimatorTest extends SdkTestCase
{
    public function testEveryCarrierThatCanCarryTheShipmentIsPriced(): void
    {
        $quotes = $this->estimator(self::success([
            self::quote('DPD', 17.87, 21.98),
            self::quote('INPOST_PACZKOMAT', 12.06, 14.83),
            self::quote('UPS', 120.0, 147.6, available: false),
            // Quoted at 0 when it can't carry it, which would otherwise be the cheapest.
            self::quote('GLS', 0.0, 0.0, shipment: false),
        ]))->quoteAll((new Draft())->setCarrierCode('DPD'), 1);

        self::assertSame([
            'DPD' => ['net' => 17.87, 'gross' => 21.98],
            'INPOST_PACZKOMAT' => ['net' => 12.06, 'gross' => 14.83],
        ], $quotes, 'Carriers the account can\'t use, or that can\'t carry it, are left out.');
    }

    public function testPolkuriersOwnAnswerIsRead(): void
    {
        $quotes = $this->estimator(self::success(\json_decode(self::fixture('order_valuation_v2.json'), true)))
            ->quoteAll((new Draft())->setCarrierCode('DPD'), 1);

        self::assertSame(['net' => 48.84, 'gross' => 60.07], $quotes['DPD']);
        self::assertSame(['AMBRO', 'INPOST', 'UPS', 'DPD', 'DHL', 'FEDEX'], \array_keys($quotes));
    }

    public function testAFailureIsReportedForTheMerchant(): void
    {
        $this->expectException(LocalizedException::class);
        $this->estimator('{"status":"error","response":"Internal error"}', 500)
            ->quoteAll((new Draft())->setCarrierCode('DPD'), 1);
    }

    /**
     * @return array<string, mixed> one carrier's quote as the API sends it
     */
    private static function quote(
        string $code,
        float $net,
        float $gross,
        bool $available = true,
        bool $shipment = true,
    ): array {
        return [
            ValuationMessage::FIELD_SERVICE_CODE => $code,
            ValuationMessage::FIELD_SERVICE_NAME => $code,
            ValuationMessage::FIELD_NET_PRICE => $net,
            ValuationMessage::FIELD_GROSS_PRICE => $gross,
            ValuationMessage::FIELD_SHIPMENT => $shipment,
            ValuationMessage::FIELD_AVAILABLE => $available,
        ];
    }

    private static function address(string $person, string $postcode): OrderAddressMessage
    {
        return new OrderAddressMessage(
            $person,
            'Prosta',
            'Warszawa',
            $postcode,
            'jan@example.com',
            '600100200',
            null,
            '1',
        );
    }

    private function estimator(string $answer, int $status = 200): CostEstimator
    {
        $mapper = $this->createStub(CreateOrderMessageMapper::class);
        $mapper->method('map')->willReturn(new CreateOrderMessage(
            'DPD',
            ShipmentTypeEnum::Box,
            [new PackMessage(40, 30, 20, 2.5, PackTypeEnum::Standard, 1)],
            self::address('Jan Kowalski', '00-838'),
            self::address('Anna Nowak', '31-147'),
        ));
        $client = $this->clientPool(new RecordingClient($answer, $status));

        return new CostEstimator($mapper, new ShipmentGateway($client, new ErrorMessageResolver()));
    }
}
