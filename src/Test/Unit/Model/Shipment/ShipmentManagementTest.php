<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\ApiEventEnum;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Mapper\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Mapper\SenderMapper;
use Polkurier\Shipping\Model\Mapper\StreetParser;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\OrderSnapshot;
use Polkurier\Shipping\Model\Shipment\Reconciler;
use Polkurier\Shipping\Model\Shipment\ShipmentManagement;
use Polkurier\Shipping\Model\ShipmentFactory;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Response\Order\CreatedOrderMessage;
use Polkurier\Webservice\Serializer\SerializerProvider;
use Polkurier\Webservice\Testing\RecordingClient;
use Psr\Log\NullLogger;

#[CoversClass(ShipmentManagement::class)]
#[CoversClass(Reconciler::class)]
#[CoversClass(OrderSnapshot::class)]
#[CoversClass(ErrorMessages::class)]
#[CoversClass(ApiEventDispatcher::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentManagementTest extends SdkTestCase
{
    private const int ORDER_ID = 42;

    /**
     * @var array<int, Shipment>
     */
    private array $saved = [];
    private RecordingClient $http;
    private MagentoShipment&MockObject $magentoShipment;
    private Order&MockObject $order;
    private RecordingEventManager $events;

    protected function setUp(): void
    {
        $this->http = new RecordingClient();
        $this->events = new RecordingEventManager();
        $this->magentoShipment = $this->createMock(MagentoShipment::class);
        $this->order = $this->createMock(Order::class);
        $this->order->method('getStoreId')->willReturn(1);
        $this->order->method('getIncrementId')->willReturn('000000042');
    }

    public function testPlacesTheOrderAndShipsItWithItsWaybills(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => 'POL0001',
            'label' => ['WB-111', 'WB-222'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
            'url_tracktrace' => 'https://track.example/WB-111',
        ]));
        $this->magentoShipment->expects(self::once())->method('ship')->willReturn(55);
        $this->order->expects(self::atLeastOnce())->method('addCommentToStatusHistory');

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertSame('POL0001', $shipment->getPolkurierNumber());
        self::assertSame(['WB-111', 'WB-222'], $shipment->getWaybills());
        self::assertSame(12.3, $shipment->getPriceGross());
        self::assertSame(55, $shipment->getShipmentId());
        self::assertSame('https://track.example/WB-111', $shipment->getTrackingUrl());

        $payload = $this->http->lastPayload();
        self::assertSame('create_order', $payload['apimethod']);
        self::assertSame(self::TOKEN, $payload['authorization']['token']);
        self::assertSame('DPD', $payload['data']['courier']);
        self::assertSame('Zam. 000000042', $payload['data']['description']);
        self::assertSame('00-001', $payload['data']['recipient']['postcode']);
        self::assertSame(2, $payload['data']['packs'][0]['amount']);

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
        self::assertSame('POL0001', $events[0]['response']->getOrderNumber());
    }

    public function testAFailingListenerDoesNotUndoAPlacedOrder(): void
    {
        $this->http->willAnswerWith(self::success([
            'order_number' => 'POL0001',
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
            'order_number' => 'POL0001',
            'label' => ['WB-111'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
        ]));
        $attempts = 0;
        $this->magentoShipment->method('ship')->willReturnCallback(function () use (&$attempts): int {
            if (++$attempts === 1) {
                throw new \RuntimeException('The order is on hold');
            }

            return 57;
        });

        $shipment = $this->management()->create(self::ORDER_ID, $this->draft());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState(), 'The Polkurier order exists regardless.');
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
            'order_number' => 'POL0001',
            'label' => ['WB-111'],
            'price_net' => 10.0,
            'price_gross' => 12.3,
            'unpaid_amount' => 0.0,
            'is_paid' => true,
        ]));
        $this->magentoShipment->expects(self::never())->method('ship');

        $shipment = $this->management()->placeForMagentoShipment(self::ORDER_ID, $this->draft());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertNull($shipment->getShipmentId(), 'Magento links its shipment once it is saved.');
        $events = $this->events->of(ApiEventEnum::OrderCreateAfter);
        self::assertCount(1, $events);
        self::assertSame('label', $events[0]['source']);
    }

    public function testForAShippingLabelAnUnconfirmedOrderStopsTheMagentoShipment(): void
    {
        $this->http->willAnswerWith('{"status":"error","status_code":1,"error_code":"UNKNOWN_ERROR","response":"Nieznany błąd"}');
        $this->magentoShipment->expects(self::never())->method('ship');

        try {
            $this->management()->placeForMagentoShipment(self::ORDER_ID, $this->draft());
            self::fail('Expected the label to be refused.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('did not confirm', $e->getMessage());
        }

        self::assertSame(ShipmentInterface::STATE_UNKNOWN, $this->onlyShipment()->getState(), 'It is reconciled later.');
        self::assertSame([], $this->events->of(ApiEventEnum::OrderCreateAfter));
    }

    public function testAPayloadTheSdkRejectsIsNeverSent(): void
    {
        $this->magentoShipment->expects(self::never())->method('ship');

        try {
            $this->management()->create(self::ORDER_ID, $this->draft()->setRecipientPhone(''));
            self::fail('Expected the draft to be refused.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('recipient.phone', $e->getMessage());
        }

        self::assertSame(0, $this->http->requestCount());
        self::assertSame(ShipmentInterface::STATE_FAILED, $this->onlyShipment()->getState());
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
            self::assertStringContainsString('recipient.postcode', $e->getMessage());
        }
        self::assertSame(ShipmentInterface::STATE_FAILED, $this->onlyShipment()->getState());
        self::assertSame([], $this->events->dispatched, 'A refused order changed nothing on the account.');
    }

    public function testAnUnknownErrorFromTheApiLeavesTheOutcomeUnknown(): void
    {
        // The API can answer UNKNOWN_ERROR, or an error with no code, after it saved the order.
        foreach (['{"status":"error","status_code":1,"error_code":"UNKNOWN_ERROR","response":"Nieznany błąd"}', '{"status":"error","response":"Błąd zapisu"}'] as $i => $answer) {
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
        $this->magentoShipment->expects(self::never())->method('ship');

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

    public function testReconcilingAdoptsTheOneMatchingOrder(): void
    {
        $shipment = $this->unknownShipment();
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('POL0007', 'Zam. 000000042', '00-001', '+48 600 700 800', 2),
            $this->remoteOrder('POL0008', 'Zam. 000000043', '00-001', '600700800', 2),
            $this->remoteOrder('POL0009', 'Zam. 000000042', '00-001', '600700800', 1),
        ])));
        $this->magentoShipment->expects(self::once())->method('ship')->willReturn(56);

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

        self::assertSame(ShipmentInterface::STATE_UNKNOWN, $shipment->getState(), 'Not listed yet is not the same as not placed.');
        self::assertStringContainsString('checked again', (string)$shipment->getLastError());
    }

    public function testReconcilingWithSeveralMatchesKeepsTheFirstAndReportsTheOthers(): void
    {
        $shipment = $this->unknownShipment();
        $this->http->willAnswerWith(self::success($this->orderList([
            $this->remoteOrder('POL0007', 'Zam. 000000042', '00-001', '600700800', 2, '-1 minute'),
            $this->remoteOrder('POL0010', 'Zam. 000000042', '00001', '600700800', 2, '-3 minutes'),
        ])));
        $this->magentoShipment->method('ship')->willReturn(58);
        $this->order->expects(self::atLeastOnce())->method('addCommentToStatusHistory');

        $shipment = $this->management()->reconcile((int)$shipment->getId());

        self::assertSame(ShipmentInterface::STATE_CREATED, $shipment->getState());
        self::assertSame('POL0010', $shipment->getPolkurierNumber(), 'The earliest is kept.');
        self::assertStringContainsString('POL0007', (string)$shipment->getLastError(), 'The others are reported as duplicates.');
        self::assertCount(1, $this->events->of(ApiEventEnum::OrderCreateAfter));
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

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array<string, mixed>
     */
    private function orderList(array $orders): array
    {
        return ['totalrows' => \count($orders), 'totalpages' => 1, 'currentpage' => 1, 'pagesize' => 100, 'result' => $orders];
    }

    /**
     * @return array<string, mixed>
     */
    private function remoteOrder(string $number, string $description, string $postcode, string $phone, int $packs, string $placed = 'now'): array
    {
        $address = ['person' => 'Anna Nowak', 'street' => 'Krótka 5', 'city' => 'Warszawa', 'postcode' => $postcode, 'email' => 'anna@example.com', 'phone' => $phone];

        return [
            'number' => $number,
            'status' => 'Przyjęte',
            'status_code' => 'P',
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
            'packs' => [['length' => 30, 'width' => 20, 'height' => 10, 'weight' => 2.5, 'type' => 'ST', 'amount' => $packs]],
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
            ->setPacks([(new Pack())->setPackType('ST')->setLength(30)->setWidth(20)->setHeight(10)->setWeight(2.5)->setAmount(2)])
            ->setServices([])
            ->setAdditionalFields([])
            ->setPickupMode(ShipmentDraftInterface::PICKUP_DROP_OFF);
    }

    private function management(): ShipmentManagement
    {
        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willReturn($this->order);

        $shipmentFactory = $this->createStub(ShipmentFactory::class);
        $shipmentFactory->method('create')->willReturnCallback(fn(): Shipment => $this->newShipment());

        $clientPool = $this->clientPool($this->http);

        return new ShipmentManagement(
            $orderRepository,
            $this->repository(),
            $shipmentFactory,
            $this->collectionFactory(),
            $this->mapper(),
            $this->createStub(DefaultDraftBuilder::class),
            $clientPool,
            $this->magentoShipment,
            new Reconciler($clientPool, $this->collectionFactory()),
            new OrderSnapshot(),
            new ErrorMessages(),
            new SerializerProvider(),
            new NullLogger(),
            new ApiEventDispatcher($this->events, new NullLogger()),
        );
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
            static fn(string $path): bool => $path === Config::XML_PATH_USE_ORIGIN_ADDRESS,
        );
        $config = new Config($scopeConfig, $this->createStub(EncryptorInterface::class));

        return new CreateOrderMessageMapper(
            new SenderMapper($scopeConfig, $config, new StreetParser()),
            $this->createStub(AddressRepositoryInterface::class),
            $this->createStub(BankAccountRepositoryInterface::class),
            $config,
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
            $collection->method('getItems')->willReturnCallback(function () use (&$filters): array {
                return \array_filter($this->saved, static function (Shipment $shipment) use (&$filters): bool {
                    $states = $filters[ShipmentInterface::STATE]['in'] ?? null;

                    return ($states === null || \in_array($shipment->getState(), $states, true))
                        && (!isset($filters[ShipmentInterface::POLKURIER_NUMBER]) || $shipment->getPolkurierNumber() !== null);
                });
            });

            return $collection;
        });

        return $factory;
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
}
