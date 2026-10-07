<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Sequence\SequenceInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\Data\ShipmentCommentInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentInterface as MagentoShipmentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Api\ShipmentCommentRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Magento\Sales\Model\Order\Shipment\Comment as ShipmentComment;
use Magento\SalesSequence\Model\Manager as SequenceManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Enum\ApiEventEnum;
use Polkurier\Shipping\Enum\ExternalOrderIdEnum;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Sdk\Validator;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\CancellationService;
use Polkurier\Shipping\Model\Shipment\Details\Formatter;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Draft\Item;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\Shipment\DraftFactory;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\Placement\AttemptRecorder;
use Polkurier\Shipping\Model\Shipment\Placement\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Shipment\Placement\DraftValidator;
use Polkurier\Shipping\Model\Shipment\Placement\ExternalIdService;
use Polkurier\Shipping\Model\Shipment\Placement\ManualEntrySaver;
use Polkurier\Shipping\Model\Shipment\Placement\PlacementService;
use Polkurier\Shipping\Model\Shipment\Placement\SenderMapper;
use Polkurier\Shipping\Model\Shipment\Placement\StreetParser;
use Polkurier\Shipping\Model\Shipment\ShipmentAgeChecker;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\ShipmentManagement;
use Polkurier\Shipping\Model\Shipment\Status\ReconciliationService;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderApplier;
use Polkurier\Shipping\Model\Shipment\Status\RemoteOrderMatcher;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
use Polkurier\Shipping\Model\ShipmentFactory;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Builder\Order\CodMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\CoverAddressMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\CreateOrderMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\GetOrdersMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\OrderAddressMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\PackMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\PickupMessageBuilderFactory;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\PointsMap;
use Polkurier\Webservice\Response\Order\CreatedOrderMessage;
use Polkurier\Webservice\Serializer\SerializerProvider;
use Polkurier\Webservice\Testing\RecordingClient;
use Psr\Log\NullLogger;

#[CoversClass(ShipmentManagement::class)]
#[CoversClass(PlacementService::class)]
#[CoversClass(DraftValidator::class)]
#[CoversClass(AttemptRecorder::class)]
#[CoversClass(ReconciliationService::class)]
#[CoversClass(StatusRefreshService::class)]
#[CoversClass(CancellationService::class)]
#[CoversClass(NativeFollowUpService::class)]
#[CoversClass(ShipmentAgeChecker::class)]
#[CoversClass(RemoteOrderMatcher::class)]
#[CoversClass(RemoteOrderApplier::class)]
#[CoversClass(ErrorMessageResolver::class)]
#[CoversClass(ShipmentGateway::class)]
#[CoversClass(ApiEventDispatcher::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentManagementTest extends SdkTestCase
{
    private const int ORDER_ID = 42;

    /**
     * @var string[] the internal remarks added to Magento shipments, as "id: remark"
     */
    private array $remarks = [];

    /**
     * @var array<int, Shipment>
     */
    private array $saved = [];

    private RecordingClient $http;
    private NativeShipmentService&MockObject $nativeShipmentService;

    /**
     * How many Magento shipments were cleared of a cancelled order's label.
     */
    private int $labelsCleared = 0;

    private bool $clearingFails = false;
    private Order&MockObject $order;
    private RecordingEventManager $events;
    private LockManagerInterface&Stub $locks;

    /**
     * @var string[] every lock and unlock, in order
     */
    private array $lockLog = [];

    private bool $lockTaken = false;
    private string $shippingMethod = 'polkurier_DPD';

    /**
     * The clock's time, or null for the real one.
     */
    private ?int $now = null;

    /**
     * Carriers the account no longer has.
     *
     * @var string[]
     */
    private array $goneCarriers = [];

    /**
     * What saves the entries typed in, once a shipment is placed; a stub unless a test watches it.
     */
    private ?ManualEntrySaver $manualEntrySaver = null;

    /**
     * Whether the carriers take the parcels at a point.
     */
    private ?PointRequirementEnum $senderPoint = null;

    /**
     * Where Magento would take the Polkurier order, or why it can't.
     *
     * @var array{qtys: array<int, float>, shipment_id: int|null, packages: array<int, array<string, mixed>>}|LocalizedException
     */
    private array|LocalizedException $target = ['qtys' => [], 'shipment_id' => null, 'packages' => []];

    /**
     * What identifies the orders to Polkurier, as configured.
     */
    private ExternalOrderIdEnum $externalOrderId = ExternalOrderIdEnum::Shipment;

    /**
     * The next number Magento's shipment numbering gives.
     */
    private int $nextShipmentNumber = 77;

    public function testAnOrderOfAnotherCarrierIsNotPlaced(): void
    {
        $this->shippingMethod = 'flatrate_flatrate';

        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
            self::fail('Only orders placed with a Polkurier method are shipped.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('#000000042', $e->getMessage());
        }
        self::assertSame(0, $this->http->requestCount(), 'Polkurier is never asked.');
    }

    public function testPlacesTheOrderAndShipsItWithItsWaybills(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => '18015-1001',
            'label' => ['WB-111', 'WB-222'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
            'url_tracktrace' => 'https://track.example/WB-111',
        ]));
        $this->nativeShipmentService->expects(self::once())->method('ship')->willReturnCallback(function (): int {
            $this->lockLog[] = 'ship';

            return 55;
        });
        $this->order->expects(self::atLeastOnce())->method('addCommentToStatusHistory');

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        // Recorded under the order's lock, sent without it, then saved as placed and shipped under it.
        self::assertSame([
            'lock polkurier_order_42',
            'unlock polkurier_order_42',
            'lock polkurier_order_42',
            'ship',
            'unlock polkurier_order_42',
        ], $this->lockLog);

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertSame('18015-1001', $shipment->getPolkurierNumber());
        self::assertSame(['WB-111', 'WB-222'], $shipment->getWaybills());
        self::assertSame(12.3, $shipment->getPriceGross());
        self::assertSame(55, $shipment->getShipmentId());
        self::assertSame(
            ['55: Polkurier order 18015-1001, with DPD, goes with this shipment. Waybills: WB-111, WB-222.'],
            $this->remarks,
        );
        self::assertSame('https://track.example/WB-111', $shipment->getTrackingUrl());

        $payload = $this->http->lastPayload();
        self::assertSame('create_order', $payload['apimethod']);
        self::assertSame(self::TOKEN, $payload['authorization']['token']);
        self::assertSame('DPD', $payload['data']['courier']);
        self::assertSame('Zam. 000000042', $payload['data']['description']);
        self::assertSame('00-001', $payload['data']['recipient']['postcode']);
        self::assertSame(2, $payload['data']['packs'][0]['amount']);
        self::assertSame('magento2-0123456789ab', $payload['data']['external_application_id']);
        self::assertSame(
            '000000077',
            $payload['data']['external_order_id'],
            'The number taken for the Magento shipment to be made.',
        );
        self::assertSame('000000042', $payload['data']['external_order_number']);
        self::assertSame('000000077', $shipment->getExternalOrderId());
        self::assertSame('000000077', $shipment->getReservedShipmentNumber(), 'For the Magento shipment it makes.');

        $request = (array)\json_decode((string)$shipment->getRequest(), true);
        self::assertSame('DPD', $request['courier'], 'The payload sent is kept with the shipment.');
        self::assertArrayNotHasKey('authorization', $request, 'The token is never stored.');
        self::assertSame(2, $shipment->getPackCount());

        $events = $this->events->of(ApiEventEnum::OrderCreateAfter);
        self::assertCount(1, $events);
        self::assertSame(1, $events[0]['store_id']);
        self::assertSame($shipment, $events[0]['shipment']);
        self::assertSame($this->order, $events[0]['order']);
        self::assertSame('create', $events[0]['source']);
        self::assertInstanceOf(CreatedOrderMessage::class, $events[0]['response']);
        self::assertSame('18015-1001', $events[0]['response']->getOrderNumber());
    }

    public function testAFailingListenerDoesNotUndoAPlacedOrder(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => '18015-1001',
            'label' => ['WB-111'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
        ]));
        $this->events->failing = true;

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertCount(1, $this->events->of(ApiEventEnum::OrderCreateAfter));
    }

    public function testAMagentoShipmentThatFailedAtPlacingCanBeCreatedLater(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => '18015-1001',
            'label' => ['WB-111'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
        ]));
        $attempts = 0;
        $this->nativeShipmentService->method('ship')->willReturnCallback(function () use (&$attempts): int {
            if (++$attempts === 1) {
                throw new \RuntimeException('The order is on hold');
            }

            return 57;
        });

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        self::assertSame(
            ShipmentInterface::STATE_CREATED,
            $shipment->getState(),
            'The Polkurier order exists regardless.',
        );
        self::assertNull($shipment->getShipmentId());
        self::assertStringContainsString('The order is on hold', (string)$shipment->getLastError());

        $shipment = $this->management()->shipOrder((int)$shipment->getId());

        self::assertSame(57, $shipment->getShipmentId());
        self::assertNull($shipment->getLastError());
        self::assertSame(1, $this->http->requestCount(), 'Nothing is sent to Polkurier again.');
    }

    public function testForAShippingLabelTheOrderIsPlacedWithoutShippingInMagento(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => '18015-1001',
            'label' => ['WB-111'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
        ]));
        $this->nativeShipmentService->expects(self::never())->method('ship');

        $shipment = $this->placing()->forNativeShipment(self::ORDER_ID, $this->draft());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertNull($shipment->getShipmentId(), 'Magento links its shipment once it is saved.');
        $events = $this->events->of(ApiEventEnum::OrderCreateAfter);
        self::assertCount(1, $events);
        self::assertSame('label', $events[0]['source']);
    }

    public function testAnOrderForAnExistingMagentoShipmentIsIdentifiedByItsNumber(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => '18015-1001',
            'label' => ['WB-111'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
        ]));

        $shipment = $this->placing()->forNativeShipment(self::ORDER_ID, $this->draft(), 9);

        self::assertSame('000000009', $this->http->lastPayload()['data']['external_order_id']);
        self::assertNull($shipment->getReservedShipmentNumber(), 'Nothing is taken: it has its number.');
        self::assertSame(77, $this->nextShipmentNumber);
    }

    public function testAnOrderTheSdkRefusesTakesNoShipmentNumber(): void
    {
        $draft = $this->draft()->setRecipientPhone('123');

        try {
            $this->management()->create(self::ORDER_ID, $draft);
            self::fail('Expected the order to be refused.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Recipient: Phone', $e->getMessage());
        }

        self::assertSame(77, $this->nextShipmentNumber, 'No number is taken for it.');
        self::assertSame(0, $this->http->requestCount(), 'Nothing is sent.');
        self::assertSame([], $this->saved);
    }

    public function testOrdersCanBeIdentifiedByTheMagentoOrdersNumber(): void
    {
        $this->externalOrderId = ExternalOrderIdEnum::Order;
        $this->http->willAnswerWith(self::success([
            'order_number' => '18015-1001',
            'label' => ['WB-111'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
        ]));
        $this->nativeShipmentService->method('ship')->willReturn(55);

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        self::assertSame('000000042', $this->http->lastPayload()['data']['external_order_id']);
        self::assertNull($shipment->getReservedShipmentNumber());
        self::assertSame(77, $this->nextShipmentNumber, 'No shipment number is taken.');
    }

    public function testForAShippingLabelAnUnconfirmedOrderStopsTheMagentoShipment(): void
    {
        $this->http->willAnswerWith(
            '{"status":"error","status_code":1,"error_code":"UNKNOWN_ERROR","response":"Nieznany błąd"}',
        );
        $this->nativeShipmentService->expects(self::never())->method('ship');

        try {
            $this->placing()->forNativeShipment(self::ORDER_ID, $this->draft());
            self::fail('Expected the label to be refused.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('did not confirm', $e->getMessage());
        }

        self::assertSame(
            ShipmentInterface::STATE_UNKNOWN,
            $this->onlyShipment()->getState(),
            'It is reconciled later.',
        );
        self::assertSame([], $this->events->of(ApiEventEnum::OrderCreateAfter));
    }

    public function testALabelForAMagentoShipmentWithAStandingPolkurierOrderIsRefusedBeforeAnythingIsPlaced(): void
    {
        $this->nativeShipmentService->method('assertFree')->willThrowException(
            new LocalizedException(__(
                'This Magento shipment already has a Polkurier order. To send another, cancel that one first.',
            )),
        );

        try {
            $this->placing()->forNativeShipment(self::ORDER_ID, $this->draft(), 56);
            self::fail('Two standing Polkurier orders on one Magento shipment.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('already has a Polkurier order', $e->getMessage());
        }

        self::assertSame([], $this->saved, 'Nothing is recorded, nothing sent.');
    }

    public function testACarrierThatTakesParcelsOnlyAtPointsNeedsTheDropOffPoint(): void
    {
        $this->senderPoint = PointRequirementEnum::Required;

        try {
            $this->placing()->forNativeShipment(self::ORDER_ID, $this->draft());
            self::fail('Polkurier would take it; the carrier would not.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('choose the drop-off point', $e->getMessage());
        }
        self::assertSame([], $this->saved, 'Nothing is recorded, nothing sent.');
    }

    public function testADropOffPointGoesOnlyToACarrierThatTakesParcelsAtPoints(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => 'POL0006',
            'label' => ['WB-666'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
        ]));
        $this->senderPoint = PointRequirementEnum::None;

        $this->placing()->forNativeShipment(self::ORDER_ID, $this->draft()->setSenderPointId('U16880214'));

        self::assertArrayNotHasKey(
            'point_id',
            \array_filter($this->http->lastPayload()['data']['sender'], static fn($v) => $v !== null && $v !== ''),
        );
    }

    public function testADropOffPointGoesOnlyWithParcelsDroppedOff(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => 'POL0007',
            'label' => ['WB-777'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
        ]));
        $this->senderPoint = PointRequirementEnum::Optional;

        $this->placing()->forNativeShipment(
            self::ORDER_ID,
            $this->draft()->setSenderPointId('U16880214')->setPickupMode(ShipmentDraftInterface::PICKUP_COURIER),
        );

        self::assertArrayNotHasKey(
            'point_id',
            \array_filter($this->http->lastPayload()['data']['sender'], static fn($v) => $v !== null && $v !== ''),
        );
    }

    public function testWhatWasTypedInIsKeptOnceTheShipmentIsPlaced(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => 'POL0005',
            'label' => ['WB-555'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
        ]));
        $draft = $this->draft();
        $keep = $this->createMock(ManualEntrySaver::class);
        $keep->expects(self::once())->method('keep')->with($draft);
        $this->manualEntrySaver = $keep;

        $this->placing()->forNativeShipment(self::ORDER_ID, $draft);
    }

    public function testARefusedShipmentKeepsNothingTypedIn(): void
    {
        $this->http->willAnswerWith('{"status":"error","response":"Maksymalne wymiary paczki to: 64cm x 41cm x 38cm"}');
        $keep = $this->createMock(ManualEntrySaver::class);
        $keep->expects(self::never())->method('keep');
        $this->manualEntrySaver = $keep;

        try {
            $this->placing()->forNativeShipment(self::ORDER_ID, $this->draft());
            self::fail('Refused.');
        } catch (LocalizedException) {
        }
    }

    public function testALabelForANewMagentoShipmentTakesItsItemsAsTheyAre(): void
    {
        // Magento has registered the shipment: its items count as shipped on the order already.
        $this->http->willAnswerWith(self::success([
            'order_number' => 'POL0003',
            'label' => ['WB-444'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
        ]));
        $this->nativeShipmentService->expects(self::never())->method('getQuantities');

        $shipment = $this->placing()->forNativeShipment(
            self::ORDER_ID,
            $this->draft()->setItems([(new Item())->setOrderItemId(5)->setQty(1.0)]),
        );

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
    }

    public function testOnlyTheChosenQuantitiesAreShippedInMagento(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => 'POL0002',
            'label' => ['WB-333'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
        ]));
        $draft = $this->draft()->setItems([(new Item())->setOrderItemId(5)->setQty(1.0)]);
        $this->target = ['qtys' => [5 => 1.0], 'shipment_id' => null, 'packages' => []];
        $this->nativeShipmentService->expects(self::once())->method('ship')
            ->with($this->order, self::isInstanceOf(ShipmentInterface::class), [5 => 1.0], null, [])
            ->willReturn(57);

        $shipment = $this->management()->create(self::ORDER_ID, $draft);

        self::assertSame([5 => 1.0], $shipment->getShipItems(), 'Kept, for a Magento shipment made later.');
        self::assertSame(57, $shipment->getShipmentId());
    }

    public function testAReplacementGoesOnTheMagentoShipmentOfTheOrderItReplaces(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => 'POL0003',
            'label' => ['WB-444'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
        ]));
        $this->target = ['qtys' => [], 'shipment_id' => 4, 'packages' => []];
        $this->nativeShipmentService->expects(self::once())->method('ship')
            ->with($this->order, self::isInstanceOf(ShipmentInterface::class), [], 4, [])
            ->willReturn(4);

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        self::assertSame(4, $shipment->getTargetShipmentId(), 'Kept, for a Magento shipment linked later.');
        self::assertSame(4, $shipment->getShipmentId());
    }

    public function testAMagentoShipmentThatCannotBeLinkedLeavesTheOrderUnlinked(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => 'POL0004',
            'label' => ['WB-555'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
        ]));
        $this->nativeShipmentService->method('ship')->willThrowException(
            new LocalizedException(__('Everything on the order is shipped.')),
        );

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState(), 'The Polkurier order stands.');
        self::assertNull($shipment->getShipmentId());
        self::assertStringContainsString('Everything on the order is shipped.', (string)$shipment->getLastError());
    }

    public function testQuantitiesTheOrderCannotShipStopTheShipmentBeforeAnythingIsSent(): void
    {
        $this->target = new LocalizedException(__('Choose at least one item to ship.'));
        $this->nativeShipmentService->expects(self::never())->method('ship');

        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
            self::fail('Expected the quantities to be refused.');
        } catch (LocalizedException $e) {
            self::assertSame('Choose at least one item to ship.', $e->getMessage());
        }

        self::assertSame(0, $this->http->requestCount());
        self::assertSame([], $this->saved, 'Nothing is recorded.');
    }

    public function testAPayloadTheSdkRejectsIsNeverSent(): void
    {
        $this->nativeShipmentService->expects(self::never())->method('ship');

        try {
            $this->management()->create(self::ORDER_ID, $this->draft()->setRecipientPhone(''));
            self::fail('Expected the draft to be refused.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Recipient: Phone', $e->getMessage());
        }

        self::assertSame(0, $this->http->requestCount());
        self::assertSame([], $this->saved, 'Refused as it is: not kept.');
    }

    public function testARefusalFromPolkurierIsReportedWithItsFields(): void
    {
        $this->http->willAnswerWith((string)\json_encode([
            'status' => 'error',
            'response' => 'Błędne dane odbiorcy',
            'status_code' => 26,
            'error_code' => 'VALIDATION_ERROR',
            'validation_errors' => ['recipient.postcode' => ['Nieprawidłowy kod pocztowy']],
        ]));

        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
            self::fail('Expected Polkurier to refuse.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Błędne dane odbiorcy', $e->getMessage());
            self::assertStringContainsString('Recipient: Postcode', $e->getMessage());
        }
        self::assertSame([], $this->saved, 'Refused as it is: not kept.');
        self::assertSame([], $this->events->dispatched, 'A refused order changed nothing on the account.');
    }

    public function testACarrierTheAccountNoLongerHasIsRefusedBeforeAnythingIsSent(): void
    {
        $draft = $this->draft();
        $this->goneCarriers = [$draft->getCarrierCode()];

        try {
            $this->management()->create(self::ORDER_ID, $draft);
            self::fail('The carrier is gone.');
        } catch (LocalizedException $e) {
            self::assertSame('This carrier is no longer available: choose another.', $e->getMessage());
        }
        self::assertSame(0, $this->http->requestCount(), 'Polkurier is never asked.');
        self::assertSame([], $this->saved);
    }

    public function testARefusalPolkurierGivesBeforeSavingAnythingIsFinal(): void
    {
        // The carrier's own checks come without an error code, before anything is saved.
        $this->http->willAnswerWith('{"status":"error","response":"Maksymalne wymiary paczki to: 64cm x 41cm x 38cm"}');

        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
            self::fail('The refusal is reported.');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            self::assertStringContainsString('Maksymalne wymiary paczki', $e->getMessage());
        }
        self::assertSame([], $this->saved, 'Nothing to send again as it is: the parcel has to change.');
    }

    public function testTheNextAttemptPlacesTheShipmentThatWasNotPlaced(): void
    {
        $this->http->willAnswerWith(
            '{"status":"error","status_code":3,"error_code":"MAINTENANCE_MODE","response":"Przerwa techniczna"}',
        );
        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
        } catch (LocalizedException) {
        }
        $this->http->willAnswerWith(self::success([
            'order_number' => '18015-1001',
            'label' => ['WB-111'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
        ]));

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        self::assertCount(1, $this->saved, 'One shipment, however many times it is tried.');
        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertNull($shipment->getLastError());
        self::assertSame(
            '000000077',
            $this->http->lastPayload()['data']['external_order_id'],
            'Sent again with the number it took.',
        );
        self::assertSame('000000077', $shipment->getReservedShipmentNumber());
    }

    public function testARefusedAttemptLeavesTheShipmentThatWasNotPlacedAsItWas(): void
    {
        $this->http->willAnswerWith(
            '{"status":"error","status_code":3,"error_code":"MAINTENANCE_MODE","response":"Przerwa techniczna"}',
        );
        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
        } catch (LocalizedException) {
        }
        $this->http->willAnswerWith('{"status":"error","response":"Maksymalne wymiary paczki to: 64cm x 41cm x 38cm"}');

        try {
            $this->management()->create(self::ORDER_ID, $this->draft()->setRecipientCity('Kraków'));
            self::fail('The refusal is reported.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Maksymalne wymiary', $e->getMessage());
        }

        self::assertCount(1, $this->saved);
        self::assertSame(ShipmentInterface::STATE_FAILED, $this->onlyShipment()->getState());
        self::assertStringContainsString('Przerwa techniczna', (string)$this->onlyShipment()->getLastError());
        self::assertNotSame(
            'Kraków',
            $this->onlyShipment()->getDraft()['recipient_city'] ?? null,
            'What was filled in then.',
        );
    }

    public function testAFailureOnPolkurierSideIsKeptToBeSentAgainAsItIs(): void
    {
        $this->http->willAnswerWith(
            '{"status":"error","status_code":3,"error_code":"MAINTENANCE_MODE","response":"Przerwa techniczna"}',
        );

        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
            self::fail('The failure is reported.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Przerwa techniczna', $e->getMessage());
        }
        self::assertSame(ShipmentInterface::STATE_FAILED, $this->onlyShipment()->getState());
        self::assertSame(
            $this->draft()->getCarrierCode(),
            $this->onlyShipment()->getDraft()['carrier_code'] ?? null,
            'Sending it again starts from what was sent.',
        );
    }

    public function testAnUnknownErrorFromTheApiLeavesTheOutcomeUnknown(): void
    {
        // The API can answer UNKNOWN_ERROR, its fatal error with no code, or no reason at all, after it
        // saved the order.
        $answers = [
            '{"status":"error","status_code":1,"error_code":"UNKNOWN_ERROR","response":"Nieznany błąd"}',
            '{"status":"error","response":"Wystąpił nieoczekiwany błąd podczas tworzenia zlecenia. '
            . 'Prosimy spróbować ponownie lub skontaktować się z biurem obsługi klienta"}',
            '{"status":"error","response":""}',
        ];
        foreach ($answers as $i => $answer) {
            $this->saved = [];
            $this->http->willAnswerWith($answer);

            $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

            self::assertSame(ShipmentInterface::STATE_UNKNOWN, $shipment->getState(), 'answer #' . $i);
        }
    }

    public function testNoAnswerLeavesTheOutcomeUnknownAndBlocksTheNextAttempt(): void
    {
        $http = new RecordingClient('<html><body>502 Bad Gateway</body></html>', 502);
        $this->http = $http;
        $this->nativeShipmentService->expects(self::never())->method('ship');

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        self::assertSame(ShipmentInterface::STATE_UNKNOWN, $shipment->getState());
        self::assertNull($shipment->getPolkurierNumber());
        self::assertNotNull($shipment->getLastError());
        self::assertSame(1, $http->requestCount());

        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
            self::fail('A second attempt must wait for reconciliation.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Reconcile', $e->getMessage());
        }
        self::assertSame(1, $http->requestCount(), 'Nothing was sent the second time.');
        self::assertSame([], $this->events->dispatched, 'Nothing is reported before the outcome is known.');
    }

    public function testNothingIsSentWhileAnotherRequestIsPlacingTheSameOrder(): void
    {
        $this->lockTaken = true;

        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
            self::fail('Only one request places an order at a time.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Another request is updating', $e->getMessage());
        }
        self::assertSame(0, $this->http->requestCount());
        self::assertSame([], $this->saved);
    }

    public function testAnAttemptStillBeingSentAfterTwoMinutesIsOneToReconcile(): void
    {
        $pending = $this->unknownShipment()->setState(ShipmentInterface::STATE_PENDING);
        $pending->setData('created_at', '2026-09-30 09:59:00');

        $reasonAt = ['2026-09-30 10:00:00' => 'being placed right now', '2026-09-30 10:03:00' => 'Reconcile it'];
        foreach ($reasonAt as $now => $reason) {
            $this->now = (int)\strtotime($now . ' UTC');
            try {
                $this->management()->create(self::ORDER_ID, $this->draft());
                self::fail('The earlier attempt holds the order.');
            } catch (LocalizedException $e) {
                self::assertStringContainsString($reason, $e->getMessage());
            }
        }
        self::assertSame(0, $this->http->requestCount());
    }

    public function testTheOrderIsFreeAgainOncePlacedOrRefused(): void
    {
        $this->management()->create(self::ORDER_ID, $this->draft());
        $this->target = new LocalizedException(__('You can ship at most 1 of "Mug".'));
        try {
            $this->management()->create(self::ORDER_ID, $this->draft());
        } catch (LocalizedException) {
        }

        self::assertSame(
            \array_merge(...\array_fill(0, 2, ['lock polkurier_order_42', 'unlock polkurier_order_42'])),
            $this->lockLog,
        );
    }

    public function testCancellingLeavesTheMagentoShipmentForAnotherPolkurierOrder(): void
    {
        $shipment = $this->placedShipment();
        $comments = $this->comments();
        $this->http->willAnswerWith(self::success(['cancellation' => true]));

        $shipment = $this->management()->cancel((int)$shipment->getId());

        self::assertSame(1, $this->labelsCleared);
        self::assertSame(ShipmentInterface::STATE_CANCELLED, $shipment->getState());
        self::assertSame(56, $shipment->getShipmentId(), 'Still linked: the shipment shows what it replaces.');
        self::assertSame(
            [
                'Polkurier order 18015-1001 cancelled. Magento shipment #000000009 stays, '
                . 'without its label and tracking numbers, for another Polkurier order.',
            ],
            $comments->getArrayCopy(),
        );
        self::assertSame(
            [
                '56: Polkurier order 18015-1001 cancelled. Its label and tracking numbers were removed; '
                . 'another Polkurier order can be placed on this shipment.',
            ],
            $this->remarks,
            'An internal remark on the shipment too.',
        );
        self::assertCount(1, $this->events->of(ApiEventEnum::OrderCancelAfter));
    }

    public function testACancellationIsKeptWhenTheLabelCanNotBeRemoved(): void
    {
        $shipment = $this->placedShipment();
        $comments = $this->comments();
        $this->http->willAnswerWith(self::success(['cancellation' => true]));
        $this->clearingFails = true;

        $shipment = $this->management()->cancel((int)$shipment->getId());

        self::assertSame(ShipmentInterface::STATE_CANCELLED, $this->saved[(int)$shipment->getId()]->getState());
        self::assertSame(56, $shipment->getShipmentId());
        self::assertSame(['Polkurier order 18015-1001 cancelled.'], $comments->getArrayCopy());
    }

    public function testACancellationFoundByTheRefreshClearsTheMagentoShipment(): void
    {
        $shipment = $this->placedShipment();
        $comments = $this->comments();
        $this->http->willAnswerWith(self::success($this->orderList([
            ['status' => 'Anulowane', 'status_code' => 'A']
                + $this->remoteOrder('18015-1001', 'Zam. 000000042', '00-001', '600700800', 1),
        ])));

        $shipment = $this->management()->refreshStatus((int)$shipment->getId());

        self::assertSame(1, $this->labelsCleared);
        self::assertSame(ShipmentInterface::STATE_CANCELLED, $shipment->getState());
        self::assertSame(56, $shipment->getShipmentId());
        self::assertSame(
            [
                'Polkurier order 18015-1001 was cancelled in Polkurier. Magento shipment #000000009 stays, '
                . 'without its label and tracking numbers, for another Polkurier order.',
            ],
            $comments->getArrayCopy(),
        );
    }

    public function testARefreshOfAnOrderStillOnItsWayKeepsTheMagentoShipment(): void
    {
        $shipment = $this->placedShipment();
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('18015-1001', 'Zam. 000000042', '00-001', '600700800', 1),
        ])));

        self::assertSame(56, $this->management()->refreshStatus((int)$shipment->getId())->getShipmentId());
        self::assertSame(0, $this->labelsCleared);
    }

    public function testReconcilingAdoptsTheOneMatchingOrder(): void
    {
        $shipment = $this->unknownShipment();
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('POL0007', 'Zam. 000000042', '00-001', '+48 600 700 800', 2),
            $this->remoteOrder('POL0008', 'Zam. 000000043', '00-001', '600700800', 2),
            $this->remoteOrder('POL0009', 'Zam. 000000042', '00-001', '600700800', 1),
        ])));
        $this->nativeShipmentService->expects(self::once())->method('ship')->willReturn(56);

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertSame('POL0007', $shipment->getPolkurierNumber());
        self::assertSame(['WB-POL0007'], $shipment->getWaybills());
        self::assertFalse($shipment->isFinal(), 'A shipment still on its way keeps being refreshed.');
        $events = $this->events->of(ApiEventEnum::OrderCreateAfter);
        self::assertCount(1, $events);
        self::assertSame('reconcile', $events[0]['source']);
        self::assertSame($shipment, $events[0]['shipment']);
        $payload = $this->http->lastPayload();
        self::assertSame('get_orders', $payload['apimethod']);
        self::assertSame('DPD', $payload['data']['courier']);
        self::assertTrue($payload['data']['packs']);
    }

    public function testAnOrderListedWithoutItsParcelsIsOnlyAPossibleMatch(): void
    {
        $shipment = $this->unknownShipment(createdMinutesAgo: 20);
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('POL0007', 'Zam. 000000042', '00-001', '600700800', 0),
        ])));
        $this->nativeShipmentService->expects(self::never())->method('ship');

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(
            ShipmentInterface::STATE_UNKNOWN,
            $shipment->getState(),
            'Neither adopted nor taken as not placed.',
        );
        self::assertNull($shipment->getPolkurierNumber());
        self::assertStringContainsString('POL0007', (string)$shipment->getLastError());
    }

    public function testAnOrderAnotherShipmentHoldsIsNotAdoptedHoweverOld(): void
    {
        $held = $this->newShipment()
            ->setOrderId(self::ORDER_ID)
            ->setState(ShipmentInterface::STATE_CREATED)
            ->setPolkurierNumber('POL0007');
        $held->setData('created_at', \gmdate('Y-m-d H:i:s', \time() - 30 * 86400));
        $this->repository()->save($held);
        $shipment = $this->unknownShipment();
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('POL0007', 'Zam. 000000042', '00-001', '600700800', 2),
        ])));

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(ShipmentInterface::STATE_UNKNOWN, $shipment->getState());
        self::assertNull($shipment->getPolkurierNumber());
    }

    public function testReconcilingWithNoMatchLongAfterwardsMakesItSafeToSendAgain(): void
    {
        $shipment = $this->unknownShipment(createdMinutesAgo: 20);
        $this->http->willAnswerWith(self::success($this->orderList([])));

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(ShipmentInterface::STATE_FAILED, $shipment->getState());
        self::assertNotNull($shipment->getStatusCheckedAt());
    }

    public function testReconcilingWithNoMatchRightAwayWaitsForPolkurierToListIt(): void
    {
        $shipment = $this->unknownShipment(createdMinutesAgo: 2);
        $this->http->willAnswerWith(self::success($this->orderList([])));

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(
            ShipmentInterface::STATE_UNKNOWN,
            $shipment->getState(),
            'Not listed yet is not the same as not placed.',
        );
        self::assertStringContainsString('checked again', (string)$shipment->getLastError());
    }

    public function testReconcilingWithSeveralMatchesKeepsTheFirstAndReportsTheOthers(): void
    {
        $shipment = $this->unknownShipment();
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('POL0007', 'Zam. 000000042', '00-001', '600700800', 2, '-1 minute'),
            $this->remoteOrder('POL0010', 'Zam. 000000042', '00001', '600700800', 2, '-3 minutes'),
        ])));
        $this->nativeShipmentService->method('ship')->willReturn(58);
        $this->order->expects(self::atLeastOnce())->method('addCommentToStatusHistory');

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertSame('POL0010', $shipment->getPolkurierNumber(), 'The earliest is kept.');
        self::assertStringContainsString(
            'POL0007',
            (string)$shipment->getLastError(),
            'The others are reported as duplicates.',
        );
        self::assertCount(1, $this->events->of(ApiEventEnum::OrderCreateAfter));
    }

    public function testAnOrderSentWithTheShopsIdsIsLookedUpByThem(): void
    {
        $shipment = $this->unknownShipment();
        $this->repository()->save($shipment->setExternalOrderId('000000077'));
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('POL0007', 'Zam. 000000042', '00-001', '600700800', 2),
        ])));
        $this->nativeShipmentService->method('ship')->willReturn(56);

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame('POL0007', $shipment->getPolkurierNumber());
        self::assertSame(1, $this->http->requestCount(), 'Found by its ids at once.');
        $data = $this->http->lastPayload()['data'];
        self::assertSame('magento2-0123456789ab', $data['external_application_id']);
        self::assertSame('000000077', $data['external_order_id']);
        self::assertArrayNotHasKey('courier', $data);
    }

    public function testAnOrderNotFoundByTheShopsIdsIsLookedForAsBefore(): void
    {
        $shipment = $this->unknownShipment(createdMinutesAgo: 20);
        $this->repository()->save($shipment->setExternalOrderId('000000077'));
        $this->http->willAnswerWith(self::success($this->orderList([])));

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(2, $this->http->requestCount(), 'Polkurier may not have kept the ids.');
        self::assertSame('DPD', $this->http->lastPayload()['data']['courier']);
        self::assertSame(ShipmentInterface::STATE_FAILED, $shipment->getState());
    }

    public function testReconcilingPrefersALiveOrderToAnEarlierCancelledOne(): void
    {
        $shipment = $this->unknownShipment();
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('POL0010', 'Zam. 000000042', '00-001', '600700800', 2, '-3 minutes', 'A'),
            $this->remoteOrder('POL0007', 'Zam. 000000042', '00-001', '600700800', 2, '-1 minute'),
        ])));
        $this->nativeShipmentService->expects(self::once())->method('ship')->willReturn(58);

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertSame('POL0007', $shipment->getPolkurierNumber());
        self::assertNull($shipment->getLastError(), 'A cancelled one is no duplicate to deal with.');
    }

    public function testAnOrderFoundAlreadyCancelledIsLinkedAsCancelledAndNotShipped(): void
    {
        $shipment = $this->unknownShipment();
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('POL0010', 'Zam. 000000042', '00-001', '600700800', 2, '-3 minutes', 'A'),
        ])));
        $this->nativeShipmentService->expects(self::never())->method('ship');

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(ShipmentInterface::STATE_CANCELLED, $shipment->getState());
        self::assertSame('POL0010', $shipment->getPolkurierNumber(), 'Held, so that it is not found again.');
        self::assertTrue($shipment->isFinal());
        self::assertSame([], $this->events->of(ApiEventEnum::OrderCreateAfter));
    }

    protected function setUp(): void
    {
        $this->http = new RecordingClient();
        $this->events = new RecordingEventManager();
        $this->nativeShipmentService = $this->createMock(NativeShipmentService::class);
        $this->nativeShipmentService->method('getTarget')->willReturnCallback(
            fn(): array => $this->target instanceof LocalizedException ? throw $this->target : $this->target,
        );
        $this->locks = $this->createStub(LockManagerInterface::class);
        $this->locks->method('lock')->willReturnCallback(function (string $name): bool {
            $this->lockLog[] = 'lock ' . $name;

            return !$this->lockTaken;
        });
        $this->locks->method('unlock')->willReturnCallback(function (string $name): bool {
            $this->lockLog[] = 'unlock ' . $name;

            return true;
        });
        $this->order = $this->createMock(Order::class);
        $this->order->method('getStoreId')->willReturn(1);
        $this->order->method('getIncrementId')->willReturn('000000042');
        $this->order->method('getEntityId')->willReturn(self::ORDER_ID);
        $this->order->method('getShippingMethod')->willReturnCallback(fn(): string => $this->shippingMethod);
    }

    private function externalIds(): ExternalIdService
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getExternalOrderId')->willReturnCallback(fn(): ExternalOrderIdEnum => $this->externalOrderId);
        $sequence = $this->createStub(SequenceInterface::class);
        $sequence->method('getNextValue')->willReturnCallback(
            fn(): string => \sprintf('%09d', $this->nextShipmentNumber++),
        );
        $sequences = $this->createStub(SequenceManager::class);
        $sequences->method('getSequence')->willReturn($sequence);
        $magentoShipments = $this->createStub(MagentoShipmentRepository::class);
        $magentoShipments->method('get')->willReturnCallback(function (int $id): MagentoShipmentInterface {
            $magentoShipment = $this->createStub(MagentoShipmentInterface::class);
            $magentoShipment->method('getIncrementId')->willReturn(\sprintf('%09d', $id));

            return $magentoShipment;
        });

        return new ExternalIdService($config, $this->applicationId(), $magentoShipments, $sequences);
    }

    private function applicationId(): ApplicationIdProvider
    {
        $applicationId = $this->createStub(ApplicationIdProvider::class);
        $applicationId->method('get')->willReturn('magento2-0123456789ab');

        return $applicationId;
    }

    private function unknownShipment(int $createdMinutesAgo = 1): Shipment
    {
        $shipment = $this->newShipment()
            ->setOrderId(self::ORDER_ID)
            ->setStoreId(1)
            ->setState(ShipmentInterface::STATE_UNKNOWN)
            ->setCarrierCode('DPD')
            ->setDescription('Zam. 000000042')
            ->setRecipientPostcode('00-001')
            ->setRecipientPhone('600700800')
            ->setPackCount(2)
            ->setWaybills([]);
        $shipment->setData('created_at', \gmdate('Y-m-d H:i:s', \time() - $createdMinutesAgo * 60));
        $this->repository()->save($shipment);

        return $shipment;
    }

    private function placedShipment(): Shipment
    {
        $shipment = $this->newShipment()
            ->setOrderId(self::ORDER_ID)
            ->setStoreId(1)
            ->setState(ShipmentInterface::STATE_CREATED)
            ->setPolkurierNumber('18015-1001')
            ->setShipmentId(56)
            ->setCarrierCode('DPD')
            ->setWaybills(['WB-18015-1001']);
        $this->repository()->save($shipment);

        return $shipment;
    }

    /**
     * @return \ArrayObject<int, string> the comments added to the order from now on
     */
    private function comments(): \ArrayObject
    {
        $comments = new \ArrayObject();
        $this->order->method('addCommentToStatusHistory')->willReturnCallback(
            function (string $comment) use ($comments): null {
                $comments->append($comment);

                return null;
            },
        );

        return $comments;
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     *
     * @return array<string, mixed>
     */
    private function orderList(array $orders): array
    {
        return [
            'totalrows' => \count($orders),
            'totalpages' => 1,
            'currentpage' => 1,
            'pagesize' => 100,
            'result' => $orders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function remoteOrder(
        string $number,
        string $description,
        string $postcode,
        string $phone,
        int $packs,
        string $placed = 'now',
        string $status = 'P',
    ): array {
        $address = [
            'person' => 'Anna Nowak',
            'street' => 'Krótka 5',
            'city' => 'Warszawa',
            'postcode' => $postcode,
            'email' => 'anna@example.com',
            'phone' => $phone,
        ];

        return [
            'number' => $number,
            'status' => $status === 'A' ? 'Anulowane' : 'Przyjęte',
            'status_code' => $status,
            'date' => (new \DateTimeImmutable($placed))->format('Y-m-d H:i:s'),
            'status_date' => \date('Y-m-d H:i:s'),
            'shipmenttype' => 'box',
            'courier' => 'DPD',
            'description' => $description,
            'sender' => ['person' => 'Sklep'] + $address,
            'recipient' => $address,
            'waybills' => [['number' => 'WB-' . $number, 'is_default' => true]],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'packs' => [
                ['length' => 30, 'width' => 20, 'height' => 10, 'weight' => 2.5, 'type' => 'ST', 'amount' => $packs],
            ],
        ];
    }

    private function draft(): Draft
    {
        return (new Draft())
            ->setCarrierCode('DPD')
            ->setShipmentType('box')
            ->setDescription('Zam. 000000042')
            ->setSenderAddressId(null)
            ->setRecipientPerson('Anna Nowak')
            ->setRecipientStreet('Krótka')
            ->setRecipientHouseNumber('5')
            ->setRecipientPostcode('00-001')
            ->setRecipientCity('Warszawa')
            ->setRecipientCountry('PL')
            ->setRecipientPhone('600700800')
            ->setRecipientEmail('anna@example.com')
            ->setPacks([
                (new Pack())
                    ->setPackType('ST')
                    ->setLength(30)
                    ->setWidth(20)
                    ->setHeight(10)
                    ->setWeight(2.5)
                    ->setAmount(2),
            ])
            ->setServices([])
            ->setAdditionalFields([])
            ->setPickupMode(ShipmentDraftInterface::PICKUP_DROP_OFF);
    }

    private function management(): ShipmentManagement
    {
        [
            'placing' => $placing,
            'reconciliation' => $reconciliation,
            'refresh' => $refresh,
            'cancellation' => $cancellation,
            'orders' => $orders,
        ] = $this->services();

        return new ShipmentManagement(
            $orders,
            $this->createStub(DefaultDraftBuilder::class),
            $placing,
            $reconciliation,
            $refresh,
            $cancellation,
        );
    }

    private function placing(): PlacementService
    {
        return $this->services()['placing'];
    }

    /**
     * The services behind ShipmentManagement, over the real SDK answering from $this->http.
     *
     * @return array{placing: PlacementService, reconciliation: ReconciliationService, refresh: StatusRefreshService, cancellation: CancellationService, orders: OrderLoader}
     */
    private function services(): array
    {
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willReturn($this->order);
        $orderRepository->expects(self::never())->method('save');

        $shipmentFactory = $this->createStub(ShipmentFactory::class);
        $shipmentFactory->method('create')->willReturnCallback(fn(): Shipment => $this->newShipment());

        $gateway = new ShipmentGateway($this->clientPool($this->http), new ErrorMessageResolver());
        $clock = new FixedDateTime($this->now);
        $events = new ApiEventDispatcher($this->events, new NullLogger());
        $reconciler = new RemoteOrderMatcher(
            $gateway,
            new ShipmentFinder($this->collectionFactory()),
            $clock,
            $this->applicationId(),
            NewingObjectManager::factory(GetOrdersMessageBuilderFactory::class),
        );
        $snapshot = new RemoteOrderApplier($clock);
        $age = new ShipmentAgeChecker($clock);
        $orderLock = new OrderLock($this->locks);
        $orders = new OrderLoader($orderRepository);
        $finder = new ShipmentFinder($this->collectionFactory());
        $catalog = $this->catalog($this->goneCarriers);
        $features = new CarrierFeatureResolver();
        [$commentFactory, $commentRepository] = $this->shipmentComments();
        $followUp = new NativeFollowUpService(
            new OrderLoader($orderRepository),
            $this->repository(),
            $this->nativeShipmentService,
            $this->magentoShipments(),
            new ShipmentFinder($this->collectionFactory()),
            $this->createStub(OrderStatusHistoryRepositoryInterface::class),
            $commentFactory,
            $commentRepository,
            $events,
            new NullLogger(),
            $this->carrierNames(),
        );

        return [
            'placing' => new PlacementService(
                $orders,
                $orderLock,
                new DraftValidator(
                    $catalog,
                    $features,
                    $this->mapper(),
                    new Validator(),
                    new ErrorMessageResolver(),
                    new NullLogger(),
                ),
                new AttemptRecorder(
                    $finder,
                    $shipmentFactory,
                    $this->repository(),
                    new SerializerProvider(),
                    new DraftHydrator(
                        $this->createStub(DraftFactory::class),
                        $this->createStub(PackFactory::class),
                        $this->createStub(ItemFactory::class),
                    ),
                    $age,
                ),
                $this->externalIds(),
                $this->mapper(),
                $this->nativeShipmentService,
                $gateway,
                new ErrorMessageResolver(),
                $followUp,
                $this->carrierNames(),
                $events,
                $this->manualEntrySaver ?? $this->createStub(ManualEntrySaver::class),
                new NullLogger(),
            ),
            'reconciliation' => new ReconciliationService(
                $this->repository(),
                $reconciler,
                $snapshot,
                $age,
                $followUp,
                $orders,
                $clock,
                $orderLock,
                $finder,
                $this->createStub(ShipmentResource::class),
                new NullLogger(),
            ),
            'refresh' => new StatusRefreshService(
                $this->repository(),
                $reconciler,
                $snapshot,
                $this->nativeShipmentService,
                $followUp,
                $orders,
                $clock,
                $this->createStub(Formatter::class),
                $orderLock,
                $age,
                $finder,
                $this->createStub(ShipmentResource::class),
                $this->createStub(ConfigProvider::class),
                new NullLogger(),
            ),
            'cancellation' => new CancellationService(
                $this->repository(),
                $gateway,
                $events,
                $followUp,
                $orders,
                $orderLock,
            ),
            'orders' => $orders,
        ];
    }

    /**
     * Carriers by the names the admin knows them by.
     */
    private function carrierNames(): CarrierNameProvider
    {
        $names = $this->createStub(CarrierNameProvider::class);
        $names->method('get')->willReturnCallback(
            static fn(string $code): string => ['INPOST_PACZKOMAT' => 'InPost Paczkomat 24/7'][$code] ?? $code,
        );

        return $names;
    }

    private function mapper(): CreateOrderMessageMapper
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['shipping/origin/street_line1', 'store', 1, 'Marszałkowska 10'],
            ['shipping/origin/street_line2', 'store', 1, ''],
            ['shipping/origin/city', 'store', 1, 'Warszawa'],
            ['shipping/origin/postcode', 'store', 1, '00-590'],
            ['shipping/origin/country_id', 'store', 1, 'PL'],
            ['general/store_information/name', 'store', 1, 'Sklep'],
            ['general/store_information/phone', 'store', 1, '221234567'],
            ['trans_email/ident_general/email', 'store', 1, 'sklep@example.com'],
        ]);
        // Shipments without a sender from the address book go from the shipping origin.
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path): bool => $path === ConfigProvider::XML_PATH_USE_ORIGIN_ADDRESS,
        );
        $config = new ConfigProvider($scopeConfig, $this->createStub(EncryptorInterface::class));

        return new CreateOrderMessageMapper(
            new SenderMapper(
                $scopeConfig,
                $config,
                new StreetParser(),
                NewingObjectManager::factory(OrderAddressMessageBuilderFactory::class),
                NewingObjectManager::factory(CoverAddressMessageBuilderFactory::class),
            ),
            $this->createStub(AddressRepositoryInterface::class),
            $this->createStub(BankAccountRepositoryInterface::class),
            $config,
            NewingObjectManager::factory(CreateOrderMessageBuilderFactory::class),
            NewingObjectManager::factory(PackMessageBuilderFactory::class),
            NewingObjectManager::factory(OrderAddressMessageBuilderFactory::class),
            NewingObjectManager::factory(CodMessageBuilderFactory::class),
            NewingObjectManager::factory(PickupMessageBuilderFactory::class),
        );
    }

    /**
     * Shipments of the in-memory repository, whatever the filter; the tests hold one order.
     */
    private function collectionFactory(): CollectionFactory
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function (): Collection {
            $filters = [];
            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                function (string $field, mixed $condition) use (&$filters, &$collection): Collection {
                    $filters[$field] = $condition;

                    return $collection;
                },
            );
            $collection->method('setOrder')->willReturnSelf();
            $collection->method('setPageSize')->willReturnSelf();
            $collection->method('getItems')->willReturnCallback(function () use (&$filters): array {
                return \array_filter($this->saved, static function (Shipment $shipment) use (&$filters): bool {
                    $states = $filters[ShipmentInterface::FIELD_STATE]['in']
                        ?? (isset($filters[ShipmentInterface::FIELD_STATE]['eq'])
                            ? [$filters[ShipmentInterface::FIELD_STATE]['eq']]
                            : null);

                    $numbers = $filters[ShipmentInterface::FIELD_POLKURIER_NUMBER]['in'] ?? null;
                    $except = $filters[ShipmentInterface::FIELD_ENTITY_ID]['neq'] ?? null;

                    return ($states === null || \in_array($shipment->getState(), $states, true))
                        && (
                            !isset($filters[ShipmentInterface::FIELD_POLKURIER_NUMBER])
                            || $shipment->getPolkurierNumber() !== null
                        )
                        && ($numbers === null || \in_array($shipment->getPolkurierNumber(), $numbers, true))
                        && ($except === null || (int)$shipment->getId() !== (int)$except);
                });
            });

            return $collection;
        });

        return $factory;
    }

    /**
     * The remarks added to Magento shipments, recorded in $this->remarks.
     *
     * @return array{ShipmentCommentInterfaceFactory, ShipmentCommentRepositoryInterface}
     */
    private function shipmentComments(): array
    {
        $factory = $this->createStub(ShipmentCommentInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            fn(): ShipmentComment => $this->getMockBuilder(ShipmentComment::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock(),
        );
        $repository = $this->createStub(ShipmentCommentRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (ShipmentComment $comment): ShipmentComment {
            $this->remarks[] = $comment->getParentId() . ': ' . $comment->getComment();

            return $comment;
        });

        return [$factory, $repository];
    }

    /**
     * Magento's shipments: #000000009 of the order, which a cancelled Polkurier order's label is
     * cleared from.
     */
    private function magentoShipments(): MagentoShipmentRepository
    {
        $repository = $this->createStub(MagentoShipmentRepository::class);
        $repository->method('get')->willReturnCallback(function (int $id): MagentoShipmentModel {
            if ($this->clearingFails) {
                throw new \RuntimeException('Deadlock');
            }
            $shipment = $this->getMockBuilder(MagentoShipmentModel::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock();

            return $shipment->setData([
                'entity_id' => $id,
                'order_id' => self::ORDER_ID,
                'increment_id' => '000000009',
            ]);
        });
        $repository->method('save')->willReturnCallback(
            function (MagentoShipmentModel $shipment): MagentoShipmentModel {
                $this->labelsCleared++;

                return $shipment;
            },
        );

        return $repository;
    }

    private function repository(): ShipmentRepositoryInterface
    {
        $repository = $this->createStub(ShipmentRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (ShipmentInterface $shipment): ShipmentInterface {
            /** @var Shipment $shipment */
            if (!$shipment->getId()) {
                $shipment->setId(\count($this->saved) + 1);
                $shipment->setData('created_at', $shipment->getData('created_at') ?: \date('Y-m-d H:i:s'));
            }
            $this->saved[(int)$shipment->getId()] = $shipment;

            return $shipment;
        });
        $repository->method('get')->willReturnCallback(
            fn(int $id): ShipmentInterface => $this->saved[$id] ?? throw new NoSuchEntityException(),
        );
        $repository->method('delete')->willReturnCallback(function (ShipmentInterface $shipment): bool {
            unset($this->saved[(int)$shipment->getId()]);

            return true;
        });

        return $repository;
    }

    private function newShipment(): Shipment
    {
        return (new ObjectManager($this))->getObject(Shipment::class);
    }

    private function onlyShipment(): Shipment
    {
        self::assertCount(1, $this->saved);

        return \reset($this->saved);
    }

    /**
     * The account's carriers: every code asked for, taking parcels, unless it's one of $gone.
     *
     * @param string[] $gone
     */
    private function catalog(array $gone = []): CarrierCatalog
    {
        $catalog = $this->createStub(CarrierCatalog::class);
        $senderPoint = $this->senderPoint;
        $catalog->method('getCarrier')->willReturnCallback(
            static fn(string $code): ?Carrier => \in_array($code, $gone, true)
                ? null
                : new Carrier($code, $code, false, new AdditionalData(
                    pointsMap: new PointsMap(senderPoint: $senderPoint),
                )),
        );

        return $catalog;
    }
}
