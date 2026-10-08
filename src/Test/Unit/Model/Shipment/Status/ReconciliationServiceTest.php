<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\ShipmentAgeChecker;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Status\ReconciliationService;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Test\Unit\Support\MemoryLocks;
use Polkurier\Webservice\Response\Order as RemoteOrder;

#[CoversClass(ReconciliationService::class)]
#[AllowMockObjectsWithoutExpectations]
class ReconciliationServiceTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $happened = [];

    public function testAnOrderLinkedByHandIsTakenAsPlacedAndShipped(): void
    {
        $shipment = $this->reconciliation(ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_CREATED)
            ->adopt(5, ' 711400-27 ');

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertNull($shipment->getLastError());
        self::assertSame([
            'find 711400-27',
            'save',
            'comment: Polkurier order 711400-27 linked by hand after an unconfirmed request.',
            'ship',
            'linked by adopt',
        ], $this->happened);
    }

    public function testAnOrderCancelledInPolkurierAlreadyLeavesTheOrderFreeToShipAgain(): void
    {
        $shipment = $this->reconciliation(ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_CANCELLED)
            ->adopt(5, '711400-27');

        self::assertSame(ShipmentInterface::STATE_CANCELLED, $shipment->getState());
        self::assertSame([
            'find 711400-27',
            'save',
            'comment: Polkurier order 711400-27 linked by hand after an unconfirmed request, already cancelled in '
            . 'Polkurier. The order can be shipped again.',
        ], $this->happened);
    }

    public function testOnlyAShipmentWhoseOutcomeIsUnknownCanBeLinked(): void
    {
        try {
            $this->reconciliation(ShipmentInterface::STATE_CREATED, ShipmentInterface::STATE_CREATED)
                ->adopt(5, '711400-27');
            self::fail('Refused before asking Polkurier.');
        } catch (LocalizedException $e) {
            self::assertSame('Only a shipment whose outcome is unknown can be linked by hand.', $e->getMessage());
        }
        self::assertSame([], $this->happened);
    }

    public function testANumberPolkurierDoesntKnowLinksNothing(): void
    {
        try {
            $this->reconciliation(ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_CREATED, found: false)
                ->adopt(5, '711400-99');
            self::fail('The number is said to be unknown.');
        } catch (LocalizedException $e) {
            self::assertSame('Polkurier has no order number 711400-99 on this account.', $e->getMessage());
        }
        self::assertSame(['find 711400-99'], $this->happened);
    }

    public function testAShipmentLinkedMeanwhileIsNotLinkedAgain(): void
    {
        try {
            $this->reconciliation(
                ShipmentInterface::STATE_UNKNOWN,
                ShipmentInterface::STATE_CREATED,
                meanwhile: ShipmentInterface::STATE_CREATED,
            )->adopt(5, '711400-27');
            self::fail('Refused once it is linked.');
        } catch (LocalizedException $e) {
            self::assertSame('Only a shipment whose outcome is unknown can be linked by hand.', $e->getMessage());
        }
        self::assertSame(['find 711400-27'], $this->happened);
    }

    public function testAShipmentReconciledMeanwhileIsLeftAsItIs(): void
    {
        $shipment = $this->reconciliation(
            ShipmentInterface::STATE_UNKNOWN,
            ShipmentInterface::STATE_CREATED,
            meanwhile: ShipmentInterface::STATE_CREATED,
        )->reconcile(5);

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertSame(['candidates'], $this->happened);
    }

    public function testAnAttemptStillWaitingForItsAnswerIsNotReconciled(): void
    {
        try {
            $this->reconciliation(ShipmentInterface::STATE_PENDING, ShipmentInterface::STATE_CREATED, old: false)
                ->reconcile(5);
            self::fail('Refused while its request may still ship it.');
        } catch (LocalizedException $e) {
            self::assertSame(
                'This shipment is still waiting for Polkurier to answer. Try again in a few minutes.',
                $e->getMessage(),
            );
        }
        self::assertSame([], $this->happened);
    }

    /**
     * @param string|null $meanwhile the state another request left it in by the time the lock is taken
     */
    private function reconciliation(
        string $state,
        string $remoteState,
        bool $found = true,
        ?string $meanwhile = null,
        bool $old = true,
    ): ReconciliationService {
        $reads = 0;
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(function () use ($state, $meanwhile, &$reads): Shipment {
            /** @var Shipment $shipment */
            $shipment = (new ObjectManager($this))->getObject(Shipment::class);

            return $shipment
                ->setId(5)
                ->setOrderId(29)
                ->setState($reads++ > 0 && $meanwhile !== null ? $meanwhile : $state)
                ->setLastError('No answer');
        });
        $repository->method('save')->willReturnCallback(function (ShipmentInterface $shipment): ShipmentInterface {
            $this->happened[] = 'save';

            return $shipment;
        });
        $reconciler = $this->createStub(RemoteOrderMatcher::class);
        $reconciler->method('findCandidates')->willReturnCallback(function (): array {
            $this->happened[] = 'candidates';

            return ['sure' => [$this->createStub(RemoteOrder::class)], 'possible' => []];
        });
        $reconciler->method('findByNumber')->willReturnCallback(function (string $number) use ($found): RemoteOrder {
            $this->happened[] = 'find ' . $number;
            if (!$found) {
                throw new LocalizedException(__('Polkurier has no order number %1 on this account.', $number));
            }

            return $this->createStub(RemoteOrder::class);
        });
        $snapshot = $this->createStub(RemoteOrderApplier::class);
        $snapshot->method('apply')->willReturnCallback(
            static function (RemoteOrder $order, ShipmentInterface $shipment) use ($remoteState): void {
                $shipment->setPolkurierNumber('711400-27')->setState($remoteState);
            },
        );
        $followUp = $this->createStub(NativeFollowUpService::class);
        $orderLoader = $this->createStub(OrderLoader::class);
        $orderLoader->method('get')->willReturn($this->createStub(Order::class));
        $followUp->method('comment')->willReturnCallback(function (Order $order, Phrase $comment): void {
            $this->happened[] = 'comment: ' . $comment;
        });
        $followUp->method('ship')->willReturnCallback(function (): ?string {
            $this->happened[] = 'ship';

            return null;
        });
        $followUp->method('linked')->willReturnCallback(
            function (ShipmentInterface $shipment, Order $order, string $source): void {
                $this->happened[] = 'linked by ' . $source;
            },
        );

        $age = $this->createStub(ShipmentAgeChecker::class);
        $age->method('isOlderThan')->willReturn($old);

        return new ReconciliationService(
            $repository,
            $reconciler,
            $snapshot,
            $age,
            $followUp,
            $orderLoader,
            new FixedDateTime(),
            new OrderLock(new MemoryLocks()),
            $this->createStub(ShipmentFinder::class),
            $this->createStub(ShipmentResource::class),
            $this->createStub(Logger::class),
        );
    }
}
