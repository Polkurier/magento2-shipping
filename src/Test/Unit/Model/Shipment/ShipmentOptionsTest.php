<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Mapper\SenderMapper;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\Shipment\ShipmentOptions;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Response\Carrier;
use Psr\Log\LoggerInterface;

#[CoversClass(ShipmentOptions::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentOptionsTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $logged = [];

    public function testAnUnreadableCarrierListSaysWhyOnceLogged(): void
    {
        $options = $this->options(new ConnectionException('Timed out.'));

        self::assertSame([], $options->getCarriers());
        self::assertSame([], $options->getCarrierFeatures());
        self::assertSame('Polkurier could not be reached, or did not answer in time: Timed out.', $options->getCarriersError());
        self::assertCount(1, $this->logged);
    }

    public function testAReadListHasNoError(): void
    {
        $options = $this->options(null);

        self::assertSame(['DPD'], \array_keys($options->getCarriers()));
        self::assertNull($options->getCarriersError());
        self::assertSame([], $this->logged);
    }

    private function options(?\Throwable $failure): ShipmentOptions
    {
        $catalog = $this->createStub(CarrierCatalog::class);
        $catalog->method('getCarriers')->willReturnCallback(static function () use ($failure): array {
            if ($failure !== null) {
                throw $failure;
            }

            return ['DPD' => new Carrier('DPD', 'DPD', false)];
        });
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function (string $message): void {
            $this->logged[] = $message;
        });

        return new ShipmentOptions($catalog, new CarrierFeatures(), $this->createStub(MasterData::class), new ErrorMessages(), $logger, $this->createStub(SenderMapper::class));
    }
}
