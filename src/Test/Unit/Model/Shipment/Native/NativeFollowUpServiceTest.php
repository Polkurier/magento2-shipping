<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Native;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\Data\ShipmentCommentInterfaceFactory;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Api\ShipmentCommentRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Helper\Data as SalesData;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Sales\Model\Order\Shipment as MagentoShipment;
use Magento\Sales\Model\Order\Shipment\Track;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface as PolkurierShipments;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService as NativeShipments;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\ShippingChoice\ShippingChoiceRepository;

#[CoversClass(NativeFollowUpService::class)]
#[AllowMockObjectsWithoutExpectations]
class NativeFollowUpServiceTest extends TestCase
{
    /**
     * @var string[] what happened, in order
     */
    private array $log = [];

    private MagentoShipment $magentoShipment;
    private bool $failing = false;

    /**
     * What Magento's sender answers: true when it sent the e-mail.
     */
    private bool $sent = true;

    /**
     * Magento's asynchronous sending of sales e-mails.
     */
    private bool $async = false;

    public function testTheChosenEmailGoesOnceThePlacedOrderHasItsWaybills(): void
    {
        $this->followUp([])->notifyCustomer($this->order(42), $this->placed(['WB-1']));

        self::assertSame(['send 56'], $this->log);
    }

    public function testTheChosenEmailWaitsForTheWaybills(): void
    {
        $this->followUp([])->notifyCustomer($this->order(42), $this->placed([]));

        self::assertSame([], $this->log);
    }

    public function testAMagentoShipmentEmailedAboutAlreadyIsNotEmailedAgain(): void
    {
        $this->magentoShipment->setData('email_sent', 1);

        $this->followUp([])->notifyCustomer($this->order(42), $this->placed(['WB-1']));

        self::assertSame([], $this->log);
    }

    public function testAnEmailMagentoCouldNotSendIsLogged(): void
    {
        $this->sent = false;

        $this->followUp([])->notifyCustomer($this->order(42), $this->placed(['WB-1']));

        self::assertSame(
            ['send 56', 'warning Magento shipment created but the e-mail to the customer was not sent'],
            $this->log,
        );
    }

    public function testAnEmailQueuedForMagentosCronIsNotAFailure(): void
    {
        $this->sent = false;
        $this->async = true;

        $this->followUp([])->notifyCustomer($this->order(42), $this->placed(['WB-1']));

        self::assertSame(['send 56'], $this->log);
    }

    public function testTheShipmentStaysWithoutTheCancelledOrdersLabelPackagesAndWaybills(): void
    {
        $cancelled = $this->polkurier(7, ShipmentInterface::STATE_CANCELLED);

        self::assertSame('000000009', $this->followUp([$cancelled])->clearShipment($this->order(42), $cancelled));
        self::assertNull($this->magentoShipment->getData('shipping_label'));
        self::assertNull($this->magentoShipment->getData('packages'));
        self::assertSame(['remove tracks WB-1', 'save 56'], $this->log);
    }

    public function testAShipmentThatCantBeClearedIsLoggedAndTheCancellationStands(): void
    {
        $cancelled = $this->polkurier(7, ShipmentInterface::STATE_CANCELLED);
        $this->failing = true;

        self::assertNull($this->followUp([$cancelled])->clearShipment($this->order(42), $cancelled));
        self::assertSame(
            ['error Polkurier order cancelled but its label could not be removed from the Magento shipment'],
            $this->log,
        );
    }

    public function testAShipmentAnotherPolkurierOrderHoldsIsLeftAlone(): void
    {
        $cancelled = $this->polkurier(7, ShipmentInterface::STATE_CANCELLED);

        self::assertNull(
            $this->followUp([$cancelled, $this->polkurier(8, ShipmentInterface::STATE_CREATED)])
                ->clearShipment($this->order(42), $cancelled),
        );
        self::assertSame([], $this->log);
    }

    public function testAShipmentOfAnotherOrderIsLeftAlone(): void
    {
        $cancelled = $this->polkurier(7, ShipmentInterface::STATE_CANCELLED);

        self::assertNull($this->followUp([$cancelled])->clearShipment($this->order(43), $cancelled));
        self::assertSame([], $this->log);
    }

    protected function setUp(): void
    {
        $this->magentoShipment = $this->getMockBuilder(MagentoShipment::class)->disableOriginalConstructor()
            ->onlyMethods(['getTracks'])->getMock();
        $this->magentoShipment->setData([
            'order_id' => 42,
            'increment_id' => '000000009',
            'shipping_label' => '%PDF',
            'packages' => [1 => []],
        ]);
        $this->magentoShipment->setId(56);
        $this->magentoShipment->method('getTracks')->willReturn([
            $this->track('WB-1', 'polkurier'),
            $this->track('WB-1', 'ups'),
            $this->track('TYPED', 'polkurier'),
        ]);
    }

    /**
     * @param Shipment[] $linked
     */
    private function followUp(array $linked): NativeFollowUpService
    {
        $shipments = $this->createStub(ShipmentRepositoryInterface::class);
        $shipments->method('get')->willReturnCallback(
            fn(): MagentoShipment => $this->failing ? throw new \RuntimeException('Deadlock') : $this->magentoShipment,
        );
        $shipments->method('save')->willReturnCallback(function (MagentoShipment $shipment): MagentoShipment {
            $this->log[] = 'save ' . $shipment->getId();

            return $shipment;
        });
        $native = $this->createStub(NativeShipments::class);
        $native->method('removeTracks')->willReturnCallback(
            function (MagentoShipment $shipment, array $waybills): void {
                $this->log[] = 'remove tracks ' . \implode(', ', $waybills);
            },
        );
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('linkedTo')->willReturn($linked);
        $logger = $this->createStub(Logger::class);
        $logger->method('error')->willReturnCallback(function (string|\Stringable $message): void {
            $this->log[] = 'error ' . $message;
        });
        $logger->method('warning')->willReturnCallback(function (string|\Stringable $message): void {
            $this->log[] = 'warning ' . $message;
        });
        $sender = $this->createStub(ShipmentSender::class);
        $sender->method('send')->willReturnCallback(function (MagentoShipment $shipment): bool {
            $this->log[] = 'send ' . $shipment->getId();

            return $this->sent;
        });
        $salesData = $this->createStub(SalesData::class);
        $salesData->method('canSendNewShipmentEmail')->willReturn(true);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn(string $path): bool => $path === 'sales_email/general/async_sending' && $this->async,
        );

        return new NativeFollowUpService(
            $this->createStub(OrderLoader::class),
            $this->createStub(PolkurierShipments::class),
            $native,
            $shipments,
            $finder,
            $this->createStub(OrderStatusHistoryRepositoryInterface::class),
            $this->createStub(ShipmentCommentInterfaceFactory::class),
            $this->createStub(ShipmentCommentRepositoryInterface::class),
            $this->createStub(ApiEventDispatcher::class),
            $logger,
            $this->createStub(CarrierNameProvider::class),
            $sender,
            $salesData,
            $scopeConfig,
            $this->createStub(ShippingChoiceRepository::class),
        );
    }

    private function polkurier(int $id, string $state): Shipment
    {
        $shipment = $this->getMockBuilder(Shipment::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $shipment->setData(['state' => $state, 'shipment_id' => 56, 'waybills' => 'WB-1'])->setId($id);

        return $shipment;
    }

    /**
     * A placed Polkurier order on Magento shipment 56, with the customer's e-mail chosen.
     *
     * @param string[] $waybills
     */
    private function placed(array $waybills): Shipment
    {
        $shipment = $this->polkurier(7, ShipmentInterface::STATE_CREATED);
        $shipment->setWaybills($waybills)->setDraft([ShipmentDraftInterface::FIELD_NOTIFY_CUSTOMER => true]);

        return $shipment;
    }

    private function order(int $id): Order
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $order->setData('entity_id', $id);

        return $order;
    }

    private function track(string $number, string $carrier): Track
    {
        $track = $this->getMockBuilder(Track::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $track->setData(['track_number' => $number, 'carrier_code' => $carrier]);

        return $track;
    }
}
