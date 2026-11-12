<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\CancellationService;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Test\Unit\Support\MemoryLocks;
use Polkurier\Webservice\Request\Order\OrderIdMessage;
use Polkurier\Webservice\Response\Order\CancellationMessage;

#[CoversClass(CancellationService::class)]
#[AllowMockObjectsWithoutExpectations]
class CancellationServiceTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $happened = [];

    private Shipment $shipment;

    public function testAPlacedOrderIsCancelledInPolkurierThenInMagento(): void
    {
        $shipment = $this->createService(ShipmentInterface::STATE_CREATED, '24680-27')->cancel(5);

        self::assertSame(ShipmentInterface::STATE_CANCELLED, $shipment->getState());
        self::assertTrue($shipment->isFinal());
        self::assertNull($shipment->getLastError());
        self::assertSame(
            ['cancel 24680-27', 'save', 'follow up here', 'event polkurier_api_order_cancel_after'],
            $this->happened,
        );
    }

    public function testAnOrderPolkurierWontCancelStaysAsItIs(): void
    {
        try {
            $this->createService(ShipmentInterface::STATE_CREATED, '24680-27', false)->cancel(5);
            self::fail('The refusal is said.');
        } catch (LocalizedException $e) {
            self::assertSame('Polkurier did not cancel order 24680-27.', $e->getMessage());
        }

        self::assertSame(ShipmentInterface::STATE_CREATED, $this->shipment->getState());
        self::assertSame(['cancel 24680-27'], $this->happened);
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function provideNotPlaced(): array
    {
        return [
            'not placed' => [ShipmentInterface::STATE_FAILED, null],
            'outcome unknown' => [ShipmentInterface::STATE_UNKNOWN, null],
            'cancelled already' => [ShipmentInterface::STATE_CANCELLED, '24680-27'],
        ];
    }

    #[DataProvider('provideNotPlaced')]
    public function testOnlyAPlacedOrderCanBeCancelled(string $state, ?string $number): void
    {
        try {
            $this->createService($state, $number)->cancel(5);
            self::fail('Refused before asking Polkurier.');
        } catch (LocalizedException $e) {
            self::assertSame('Only a placed Polkurier order can be cancelled.', $e->getMessage());
        }

        self::assertSame([], $this->happened);
    }

    public function testAnOrderARefreshFoundCancelledMeanwhileIsNotFollowedUpAgain(): void
    {
        $shipment = $this->createService(ShipmentInterface::STATE_CREATED, '24680-27', cancelledMeanwhile: true)
            ->cancel(5);

        self::assertSame(ShipmentInterface::STATE_CANCELLED, $shipment->getState());
        self::assertSame(['cancel 24680-27', 'event polkurier_api_order_cancel_after'], $this->happened);
    }

    private function createService(
        string $state,
        ?string $number,
        bool $cancelled = true,
        bool $cancelledMeanwhile = false,
    ): CancellationService {
        $this->shipment = (new ObjectManager($this))->getObject(Shipment::class);
        $this->shipment
            ->setId(5)
            ->setOrderId(29)
            ->setState($state)
            ->setPolkurierNumber($number)
            ->setLastError('Earlier failure');
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $reads = 0;
        $repository->method('get')->willReturnCallback(function () use ($cancelledMeanwhile, &$reads): Shipment {
            if ($reads++ > 0 && $cancelledMeanwhile) {
                $this->shipment->setState(ShipmentInterface::STATE_CANCELLED);
            }

            return $this->shipment;
        });
        $repository->method('save')->willReturnCallback(function (ShipmentInterface $shipment): ShipmentInterface {
            $this->happened[] = 'save';

            return $shipment;
        });
        $gateway = $this->createStub(ShipmentGateway::class);
        $gateway->method('cancelOrder')->willReturnCallback(
            function (OrderIdMessage $message) use ($cancelled): CancellationMessage {
                $this->happened[] = 'cancel ' . $message->getOrderNo();

                return new CancellationMessage($cancelled);
            },
        );
        $followUp = $this->createStub(NativeFollowUpService::class);
        $orderLoader = $this->createStub(OrderLoader::class);
        $orderLoader->method('get')->willReturn($this->createStub(Order::class));
        $followUp->method('followCancellation')->willReturnCallback(
            function (ShipmentInterface $shipment, bool $inPolkurier): Order {
                $this->happened[] = $inPolkurier ? 'follow up there' : 'follow up here';

                return $this->createStub(Order::class);
            },
        );
        $events = $this->createStub(ApiEventDispatcher::class);
        $events->method('dispatch')->willReturnCallback(function (ApiEventEnum $event): void {
            $this->happened[] = 'event ' . $event->value;
        });

        return new CancellationService(
            $repository,
            $gateway,
            $events,
            $followUp,
            $orderLoader,
            new OrderLock(new MemoryLocks()),
        );
    }
}
