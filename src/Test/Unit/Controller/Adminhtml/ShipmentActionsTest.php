<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url\EncoderInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Controller\Adminhtml\Config\TestConnection;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\MassPickup;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Pack;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Save;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\Shipment\AutoPacker;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\Pack as DraftPack;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\OwnOrders;
use Polkurier\Shipping\Model\Shipment\Pickups;
use Polkurier\Shipping\Model\Shipment\RequestedShipments;
use Polkurier\Shipping\Model\Shipment\RejectedDraft;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;
use Polkurier\Webservice\Api\Service\UtilsServiceInterface;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Http\Request\AuthorizationMessage;
use Polkurier\Webservice\Response\Util\AuthConfirmationMessage;

#[CoversClass(Save::class)]
#[CoversClass(Pack::class)]
#[CoversClass(MassPickup::class)]
#[CoversClass(TestConnection::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentActionsTest extends AdminActionTestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $kept = null;

    public function testAPlacedShipmentOpensItsPolkurierOrderWithItsWaybills(): void
    {
        $this->post = ['order_id' => '29'];
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('create')->willReturn($this->shipment(ShipmentInterface::STATE_CREATED, 'POL1', ['WB-1', 'WB-2']));

        $this->save($management)->execute();

        self::assertSame(['*/*/view', ['id' => 41]], $this->redirect);
        self::assertSame(['Polkurier order POL1 placed. Waybills: WB-1, WB-2.'], $this->messages['success']);
        self::assertNull($this->kept);
    }

    public function testARefusedShipmentReopensTheScreenWithWhatWasEntered(): void
    {
        $this->post = ['order_id' => '29', 'recipient_phone' => '600'];
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('create')->willThrowException(new LocalizedException(\__('Polkurier refused: telefon')));

        $this->save($management)->execute();

        self::assertSame(['*/*/create', ['order_id' => 29]], $this->redirect);
        self::assertSame(['Polkurier refused: telefon'], $this->messages['error']);
        self::assertSame(['order_id' => '29', 'recipient_phone' => '600'], $this->kept);
    }

    public function testAnUnconfirmedShipmentOpensWithAWarningToReconcileIt(): void
    {
        $this->post = ['order_id' => '29'];
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('create')->willReturn($this->shipment(ShipmentInterface::STATE_UNKNOWN, null, [], 'Timed out.'));

        $this->save($management)->execute();

        self::assertSame(['*/*/view', ['id' => 41]], $this->redirect);
        self::assertStringContainsString('use "Reconcile"', $this->messages['warning'][0]);
        self::assertStringEndsWith('(Timed out.)', $this->messages['warning'][0]);
        self::assertArrayNotHasKey('success', $this->messages);
    }

    public function testPackingWithoutChosenItemsPacksWhatTheOrderHasLeft(): void
    {
        $this->params = ['data' => ['order_id' => '29']];
        $packer = $this->createMock(AutoPacker::class);
        $packer->expects(self::once())->method('pack')->with(self::anything(), [5 => 2.0])
            ->willReturn(['packs' => [(new DraftPack())->setAmount(1)], 'unmeasured' => [], 'unweighed' => ['Mug'], 'unweighed_own' => [], 'templates' => true]);

        $this->pack($packer, [5 => 2.0])->execute();

        self::assertFalse($this->json['error'] ?? true);
        self::assertTrue($this->json['estimated']);
        self::assertSame('These products have no weight, so their parcels take the pack template\'s weight: Mug.', $this->json['message']);
    }

    public function testAParcelOfItsOwnSizeWithoutAWeightIsToBeWeighedByHand(): void
    {
        $this->params = ['data' => ['order_id' => '29']];
        $packer = $this->createStub(AutoPacker::class);
        $packer->method('pack')->willReturn(['packs' => [(new DraftPack())->setAmount(1)], 'unmeasured' => [], 'unweighed' => ['Mug', 'Rug'], 'unweighed_own' => ['Rug'], 'templates' => true]);

        $this->pack($packer, [5 => 2.0])->execute();

        self::assertSame(
            'These products have no weight, so their parcels take the pack template\'s weight: Mug.'
            . ' These products have no weight and their parcels have no pack template, so enter those parcels\' weights yourself: Rug.',
            $this->json['message'],
        );
    }

    public function testNothingLeftToShipIsSaidSo(): void
    {
        $this->params = ['data' => ['order_id' => '29']];
        $packer = $this->createMock(AutoPacker::class);
        $packer->expects(self::never())->method('pack');

        $this->pack($packer, [])->execute();

        self::assertSame(['error' => true, 'message' => 'Everything in this order is shipped already.'], $this->json);
    }

    public function testAMagentoShipmentThereIsPacksItsOwnItems(): void
    {
        // Its items count as shipped on the order: what the order has left is not what it carries.
        $this->params = ['data' => ['order_id' => '29', 'target_shipment_id' => '11']];
        $packer = $this->createMock(AutoPacker::class);
        $packer->expects(self::once())->method('pack')->with(self::anything(), [7 => 1.0])
            ->willReturn(['packs' => [(new DraftPack())->setAmount(1)], 'unmeasured' => [], 'unweighed' => [], 'unweighed_own' => [], 'templates' => true]);

        $this->pack($packer, [], 11, [7 => 1.0])->execute();

        self::assertFalse($this->json['error'] ?? true);
    }

    public function testUnmeasuredProductsAreNamed(): void
    {
        $this->params = ['data' => ['order_id' => '29']];
        $packer = $this->createStub(AutoPacker::class);
        $packer->method('pack')->willReturn(['packs' => [], 'unmeasured' => ['Mug', 'Lamp'], 'unweighed' => [], 'unweighed_own' => [], 'templates' => true]);

        $this->pack($packer, [5 => 2.0])->execute();

        self::assertSame(['error' => true, 'message' => 'Add shipping dimensions to these products to pack them automatically: Mug, Lamp.'], $this->json);
    }

    public function testAMaskedTokenIsTheSavedOne(): void
    {
        $this->params = ['customer_id' => ' 12345 ', 'api_token' => '******'];
        $asked = null;

        $this->testConnection(function (AuthorizationMessage $authorization) use (&$asked): AuthConfirmationMessage {
            $asked = $authorization;

            return new AuthConfirmationMessage(true);
        })->execute();

        self::assertSame(['12345', 'saved-token'], [$asked?->getLogin(), $asked?->getToken()]);
        self::assertSame(['success' => true, 'message' => 'Polkurier accepted the credentials.'], $this->json);
    }

    public function testNothingIsAskedWithoutCredentials(): void
    {
        $this->params = ['customer_id' => '', 'api_token' => ''];
        $called = false;

        $this->testConnection(function () use (&$called): AuthConfirmationMessage {
            $called = true;

            return new AuthConfirmationMessage(true);
        }, '', '')->execute();

        self::assertFalse($called);
        self::assertSame(['success' => false, 'message' => 'Enter the customer ID and the API token.'], $this->json);
    }

    public function testAFailedCheckSaysWhy(): void
    {
        $this->params = ['customer_id' => '12345', 'api_token' => 'typed'];

        $this->testConnection(static function (): AuthConfirmationMessage {
            throw new ConnectionException('Timed out.');
        })->execute();

        self::assertSame(['success' => false, 'message' => 'Polkurier could not be reached, or did not answer in time: Timed out.'], $this->json);
    }

    public function testOnlyShipmentsAwaitingACollectivePickupGoToItsForm(): void
    {
        $this->massPickup([
            $this->collective(41, 'POL1', awaiting: true),
            $this->collective(42, 'POL2', awaiting: false),
        ])->execute();

        self::assertSame('*/*/pickupForm', $this->redirect[0] ?? null);
        self::assertSame('41', $this->redirect[1]['ids'] ?? null);
        self::assertSame(['Left out, as they don\'t await a collective pickup: POL2.'], $this->messages['notice']);
    }

    public function testNoFormOpensWithoutAShipmentAwaitingACollectivePickup(): void
    {
        $this->massPickup([$this->collective(42, 'POL2', awaiting: false)])->execute();

        self::assertNotSame('*/*/pickupForm', $this->redirect[0] ?? null);
        self::assertSame(['None of the selected shipments awaits a collective pickup.'], $this->messages['error']);
    }

    /**
     * @param ShipmentInterface[] $selected
     */
    private function massPickup(array $selected): MassPickup
    {
        $requested = $this->createStub(RequestedShipments::class);
        $requested->method('getSelected')->willReturn($selected);

        return new MassPickup(
            $this->context(),
            $requested,
            $this->createStub(EncoderInterface::class),
            (new \ReflectionClass(Pickups::class))->newInstanceWithoutConstructor(),
        );
    }

    private function collective(int $id, string $number, bool $awaiting): ShipmentInterface
    {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getId')->willReturn($id);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getState')->willReturn(ShipmentInterface::STATE_CREATED);
        $shipment->method('isMultiPickup')->willReturn($awaiting);
        $shipment->method('isPickupBooked')->willReturn(false);

        return $shipment;
    }

    private function save(ShipmentManagementInterface $management): Save
    {
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function (string $key, mixed $data): void {
            $this->kept = $data;
        });
        $hydrator = $this->createStub(DraftHydrator::class);
        $hydrator->method('fromArray')->willReturn($this->createStub(ShipmentDraftInterface::class));

        return new Save($this->context(), $management, $hydrator, new RejectedDraft($persistor));
    }

    /**
     * @param array<int, float> $left what the order has left to ship
     * @param array<int, float> $inShipment what the Magento shipment of the draft carries
     */
    private function pack(AutoPacker $packer, array $left, ?int $targetId = null, array $inShipment = []): Pack
    {
        $orders = $this->createStub(OwnOrders::class);
        $orders->method('get')->willReturn($this->createStub(Order::class));
        $draft = new Draft();
        $draft->setItems([])->setShipmentType('box')->setTargetShipmentId($targetId);
        $hydrator = $this->createStub(DraftHydrator::class);
        $hydrator->method('fromArray')->willReturn($draft);
        $hydrator->method('toArray')->willReturn([ShipmentDraftInterface::PACKS => [['amount' => 1]]]);
        $magentoShipment = $this->createStub(MagentoShipment::class);
        $magentoShipment->method('getQuantities')->willReturn([]);
        $magentoShipment->method('getQuantitiesLeft')->willReturn($left);
        $magentoShipment->method('getShipmentQuantities')->willReturn($inShipment);

        return new Pack($this->context(), $this->jsonFactory(), $orders, $hydrator, $magentoShipment, $packer);
    }

    private function testConnection(callable $answer, string $savedLogin = '12345', string $savedToken = 'saved-token'): TestConnection
    {
        $utils = $this->createStub(UtilsServiceInterface::class);
        $utils->method('testAuthApi')->willReturnCallback($answer);
        $pool = $this->createStub(ClientPool::class);
        $pool->method('utils')->willReturn($utils);
        $config = $this->createStub(Config::class);
        $config->method('getLogin')->willReturn($savedLogin);
        $config->method('getToken')->willReturn($savedToken);
        return new TestConnection($this->context(), $this->jsonFactory(), $pool, $config, new ErrorMessages());
    }

    /**
     * @param string[] $waybills
     */
    private function shipment(string $state, ?string $number, array $waybills, ?string $error = null): ShipmentInterface
    {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getState')->willReturn($state);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getWaybills')->willReturn($waybills);
        $shipment->method('getLastError')->willReturn($error);
        $shipment->method('getId')->willReturn(41);

        return $shipment;
    }
}
