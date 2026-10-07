<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Native\ActualShippingUpdater;
use Polkurier\Shipping\Observer\FollowShipmentCarrierObserver;
use Psr\Log\LoggerInterface;

#[CoversClass(FollowShipmentCarrierObserver::class)]
#[AllowMockObjectsWithoutExpectations]
class FollowShipmentCarrierObserverTest extends TestCase
{
    /**
     * @var int[]
     */
    private array $synced = [];

    /**
     * @var array<int, array{string, array<string, mixed>}>
     */
    private array $errors = [];

    /**
     * @return array<string, array{string|null, string, bool}>
     */
    public static function changes(): array
    {
        return [
            'placed' => [ShipmentInterface::STATE_PENDING, ShipmentInterface::STATE_CREATED, true],
            'found after an unanswered request' => [
                ShipmentInterface::STATE_UNKNOWN,
                ShipmentInterface::STATE_CREATED,
                true,
            ],
            'cancelled' => [ShipmentInterface::STATE_CREATED, ShipmentInterface::STATE_CANCELLED, true],
            'refused' => [ShipmentInterface::STATE_PENDING, ShipmentInterface::STATE_FAILED, false],
            'saved again as it was' => [ShipmentInterface::STATE_CREATED, ShipmentInterface::STATE_CREATED, false],
            'recorded before being sent' => [null, ShipmentInterface::STATE_PENDING, false],
        ];
    }

    #[DataProvider('changes')]
    public function testTheOrdersShippingFollowsOnlyWhenAPolkurierOrderStartsOrStopsCounting(
        ?string $was,
        string $is,
        bool $follows,
    ): void {
        $this->observer()->execute($this->event($was, $is));

        self::assertSame($follows ? [29] : [], $this->synced);
    }

    public function testAFailureIsLoggedWithItsException(): void
    {
        $this->observer(new \RuntimeException('Deadlock'))
            ->execute($this->event(ShipmentInterface::STATE_PENDING, ShipmentInterface::STATE_CREATED));

        self::assertCount(1, $this->errors);
        self::assertInstanceOf(\RuntimeException::class, $this->errors[0][1]['exception']);
    }

    private function event(?string $was, string $is): Observer
    {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);
        $shipment->setOrderId(29);
        if ($was !== null) {
            $shipment->setState($was);
            $shipment->setOrigData(ShipmentInterface::FIELD_STATE, $was);
        }
        $shipment->setState($is);

        return new Observer(['event' => new Event(['shipment' => $shipment])]);
    }

    private function observer(?\Throwable $failure = null): FollowShipmentCarrierObserver
    {
        $shipping = $this->createStub(ActualShippingUpdater::class);
        $shipping->method('sync')->willReturnCallback(function (int $orderId) use ($failure): void {
            if ($failure !== null) {
                throw $failure;
            }
            $this->synced[] = $orderId;
        });
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function (string|\Stringable $message, array $context = []): void {
            $this->errors[] = [(string)$message, $context];
        });

        return new FollowShipmentCarrierObserver($shipping, $logger);
    }
}
