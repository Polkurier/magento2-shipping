<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url\EncoderInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Controller\Adminhtml\Config\TestConnection;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\MassPickup;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Pack;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\RequestedShipments;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Save;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\Pack as DraftPack;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Packing\AutoPacker;
use Polkurier\Shipping\Model\Shipment\Packing\ItemDimensionsProvider;
use Polkurier\Shipping\Model\Shipment\PickupService;
use Polkurier\Shipping\Model\Shipment\Placement\ManualEntrySaver;
use Polkurier\Shipping\Model\Shipment\Placement\RejectedDraftStorage;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Webservice\Api\Service\UtilsServiceInterface;
use Polkurier\Webservice\Enum\Http\ResponseStatusEnum;
use Polkurier\Webservice\Exception\AuthorizationException;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Http\Request\AuthorizationMessage;
use Polkurier\Webservice\Http\Request\AuthorizationMessageFactory;
use Polkurier\Webservice\Http\Response\ErrorMessage;
use Polkurier\Webservice\Response\Util\AuthConfirmationMessage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(Save::class)]
#[CoversClass(Pack::class)]
#[CoversClass(MassPickup::class)]
#[CoversClass(TestConnection::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentActionsTest extends AdminActionTestCase
{
    private Draft $draft;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $kept = null;

    public function testAPlacedShipmentOpensItsPolkurierOrderWithItsWaybills(): void
    {
        $this->post = ['order_id' => '29'];
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('create')
            ->willReturn($this->createShipment(ShipmentInterface::STATE_CREATED, 'POL1', ['WB-1', 'WB-2']));

        $this->save($management)->execute();

        self::assertSame(['*/*/view', ['id' => 41]], $this->redirect);
        self::assertSame(['Polkurier order POL1 placed. Waybills: WB-1, WB-2.'], $this->messages['success']);
        self::assertNull($this->kept);
    }

    public function testARefusedShipmentReopensTheScreenWithWhatWasEntered(): void
    {
        $this->post = ['order_id' => '29', 'recipient_phone' => '600'];
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('create')->willThrowException(new LocalizedException(__('Polkurier refused: telefon')));

        $this->save($management)->execute();

        self::assertSame(['*/*/create', ['order_id' => 29]], $this->redirect);
        self::assertSame(['Polkurier refused: telefon'], $this->messages['error']);
        self::assertSame(['order_id' => '29', 'recipient_phone' => '600'], $this->kept);
    }

    public function testWhatTheAdminMayNotChangeIsntKept(): void
    {
        $this->post = ['order_id' => '29'];
        $this->denied = ['Polkurier_Shipping::addresses', 'Polkurier_Shipping::pack_templates'];
        $draft = (new Draft())
            ->setNewSender(true)
            ->setKeepingNewSender(true)
            ->setNewSenderName('Magazyn')
            ->setNewBankAccount(true)
            ->setKeepingNewBankAccount(true)
            ->setNewBankAccountNumber('61 1090 1014 0000 0712 1981 2874')
            ->setPacks([(new DraftPack())->setKeepAs('Tuba'), (new DraftPack())->setTemplateId(3)->setKeepAs('')]);
        $management = $this->createMock(ShipmentManagementInterface::class);
        $management->expects(self::once())
            ->method('create')
            ->with(29, self::callback(
                static fn(Draft $kept): bool => !$kept->isKeepingNewSender()
                    && $kept->isKeepingNewBankAccount()
                    && $kept->getPacks()[0]->getKeepAs() === '',
            ))
            ->willReturn($this->createShipment(ShipmentInterface::STATE_CREATED, 'POL1', ['WB-1']));

        $this->save($management, $draft)->execute();

        self::assertSame([
            '"Magazyn" wasn\'t saved to addresses: you aren\'t allowed to change them.',
            '"Tuba" wasn\'t saved to pack templates: you aren\'t allowed to change them.',
        ], $this->messages['warning']);
    }

    public function testAnUnconfirmedShipmentOpensWithAWarningThatItsLookedUp(): void
    {
        $this->post = ['order_id' => '29'];
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('create')
            ->willReturn($this->createShipment(ShipmentInterface::STATE_UNKNOWN, null, [], 'Timed out.'));

        $this->save($management)->execute();

        self::assertSame(['*/*/view', ['id' => 41]], $this->redirect);
        self::assertStringContainsString('looked up in Polkurier automatically', $this->messages['warning'][0]);
        self::assertStringEndsWith('(Timed out.)', $this->messages['warning'][0]);
        self::assertArrayNotHasKey('success', $this->messages);
    }

    public function testPackingWithoutChosenItemsPacksWhatTheOrderHasLeft(): void
    {
        $this->params = ['data' => ['order_id' => '29']];
        $packer = $this->createPacker([5 => 2.0]);
        $packer->expects(self::once())->method('pack')->with(self::anything(), [5 => 2.0])
            ->willReturn([
                'packs' => [(new DraftPack())->setAmount(1)],
                'unmeasured' => [],
                'unweighed' => ['Mug'],
                'unweighed_own' => [],
            ]);

        $this->pack($packer)->execute();

        self::assertFalse($this->json['error'] ?? true);
        self::assertSame(
            'These products have no weight, so their parcels take the pack template\'s weight: Mug.',
            $this->json['message'],
        );
    }

    public function testAParcelOfItsOwnSizeWithoutAWeightIsToBeWeighedByHand(): void
    {
        $this->params = ['data' => ['order_id' => '29']];
        $packer = $this->createPacker([5 => 2.0]);
        $packer->method('pack')
            ->willReturn([
                'packs' => [(new DraftPack())->setAmount(1)],
                'unmeasured' => [],
                'unweighed' => ['Mug', 'Rug'],
                'unweighed_own' => ['Rug'],
            ]);

        $this->pack($packer)->execute();

        self::assertSame(
            'These products have no weight, so their parcels take the pack template\'s weight: Mug. '
            . 'These products have no weight and their parcels have no pack '
            . 'template, so enter those parcels\' weights yourself: Rug.',
            $this->json['message'],
        );
    }

    public function testNothingLeftToShipIsSaidSo(): void
    {
        $this->params = ['data' => ['order_id' => '29']];
        $packer = $this->createPacker([]);
        $packer->expects(self::never())->method('pack');

        $this->pack($packer)->execute();

        self::assertSame(['error' => true, 'message' => 'Everything in this order is shipped already.'], $this->json);
    }

    public function testAMagentoShipmentThereIsPacksItsOwnItems(): void
    {
        // Its items count as shipped on the order: what the order has left is not what it carries.
        $this->params = ['data' => ['order_id' => '29', 'target_shipment_id' => '11']];
        $packer = $this->createPacker([], 11, [7 => 1.0]);
        $packer->expects(self::once())->method('pack')->with(self::anything(), [7 => 1.0])
            ->willReturn([
                'packs' => [(new DraftPack())->setAmount(1)],
                'unmeasured' => [],
                'unweighed' => [],
                'unweighed_own' => [],
            ]);

        $this->pack($packer)->execute();

        self::assertFalse($this->json['error'] ?? true);
    }

    public function testUnmeasuredProductsAreNamed(): void
    {
        $this->params = ['data' => ['order_id' => '29']];
        $packer = $this->createPacker([5 => 2.0]);
        $packer->method('pack')
            ->willReturn([
                'packs' => [],
                'unmeasured' => ['Mug', 'Lamp'],
                'unweighed' => [],
                'unweighed_own' => [],
            ]);

        $this->pack($packer)->execute();

        self::assertSame(
            [
                'error' => true,
                'message' => 'Add shipping dimensions to these products to pack them automatically: Mug, Lamp.',
            ],
            $this->json,
        );
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
        self::assertSame(['error' => false, 'message' => 'Polkurier accepted the credentials.'], $this->json);
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
        self::assertSame(['error' => true, 'message' => 'Enter the customer ID and the API token.'], $this->json);
    }

    public function testAFailedCheckSaysWhy(): void
    {
        $this->params = ['customer_id' => '12345', 'api_token' => 'typed'];

        $this->testConnection(static function (): AuthConfirmationMessage {
            throw new ConnectionException('Timed out.');
        })->execute();

        self::assertSame(
            ['error' => true, 'message' => 'Polkurier could not be reached, or did not answer in time: Timed out.'],
            $this->json,
        );
    }

    public function testRejectedCredentialsAreSaidToBeTheOnesEntered(): void
    {
        $this->params = ['customer_id' => '12345', 'api_token' => 'typed'];

        $this->testConnection(function (): AuthConfirmationMessage {
            throw new AuthorizationException(
                new ErrorMessage(ResponseStatusEnum::Error, 'Błędny token', 26),
                $this->createStub(RequestInterface::class),
                $this->createStub(ResponseInterface::class),
            );
        })->execute();

        self::assertSame(
            ['error' => true, 'message' => 'Polkurier did not accept this customer ID and API token. (Błędny token)'],
            $this->json,
        );
    }

    public function testOnlyShipmentsAwaitingACollectivePickupGoToItsForm(): void
    {
        $this->createMassPickup([
            $this->createCollectiveShipment(41, 'POL1', awaiting: true),
            $this->createCollectiveShipment(42, 'POL2', awaiting: false),
        ])->execute();

        self::assertSame('*/*/pickupForm', $this->redirect[0] ?? null);
        self::assertSame('41', $this->redirect[1]['ids'] ?? null);
        self::assertSame(['Left out, as they don\'t await a collective pickup: POL2.'], $this->messages['notice']);
    }

    public function testNoFormOpensWithoutAShipmentAwaitingACollectivePickup(): void
    {
        $this->createMassPickup([$this->createCollectiveShipment(42, 'POL2', awaiting: false)])->execute();

        self::assertNotSame('*/*/pickupForm', $this->redirect[0] ?? null);
        self::assertSame(['None of the selected shipments awaits a collective pickup.'], $this->messages['error']);
    }

    /**
     * @param ShipmentInterface[] $selected
     */
    private function createMassPickup(array $selected): MassPickup
    {
        $requested = $this->createStub(RequestedShipments::class);
        $requested->method('getSelected')->willReturn($selected);

        return new MassPickup(
            $this->createContext(),
            $requested,
            $this->createStub(EncoderInterface::class),
            (new \ReflectionClass(PickupService::class))->newInstanceWithoutConstructor(),
        );
    }

    private function createCollectiveShipment(int $id, string $number, bool $awaiting): ShipmentInterface
    {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getId')->willReturn($id);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getState')->willReturn(ShipmentInterface::STATE_CREATED);
        $shipment->method('isMultiPickup')->willReturn($awaiting);
        $shipment->method('isPickupBooked')->willReturn(false);

        return $shipment;
    }

    private function save(ShipmentManagementInterface $management, ?ShipmentDraftInterface $draft = null): Save
    {
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function (string $key, mixed $data): void {
            $this->kept = $data;
        });
        $hydrator = $this->createStub(DraftHydrator::class);
        $hydrator->method('createFromArray')->willReturn($draft ?? $this->createStub(ShipmentDraftInterface::class));

        return new Save(
            $this->createContext(),
            $management,
            $hydrator,
            new RejectedDraftStorage($persistor),
            $this->createStub(ManualEntrySaver::class),
            $this->createFlashMessageCollectorFactory(),
        );
    }

    private function pack(AutoPacker $packer): Pack
    {
        $orders = $this->createStub(OrderLoader::class);
        $orders->method('getForPolkurier')->willReturn($this->createStub(Order::class));
        $hydrator = $this->createStub(DraftHydrator::class);
        $hydrator->method('createFromArray')->willReturn($this->draft);
        $hydrator->method('convertToArray')->willReturn([ShipmentDraftInterface::FIELD_PACKS => [['amount' => 1]]]);

        return new Pack($this->createContext(), $this->createJsonFactory(), $orders, $hydrator, $packer);
    }

    /**
     * The packer with what the order has left, or the Magento shipment carries; its packing itself
     * is stubbed, as {@see \Polkurier\Shipping\Test\Unit\Model\Shipment\Packing\AutoPackerTest}'s.
     *
     * @param array<int, float> $left what the order has left to ship
     * @param array<int, float> $inShipment what the Magento shipment of the draft carries
     */
    private function createPacker(array $left, ?int $targetId = null, array $inShipment = []): AutoPacker&MockObject
    {
        $this->draft = new Draft();
        $this->draft->setItems([])->setShipmentType('box')->setTargetShipmentId($targetId);
        $nativeShipments = $this->createStub(NativeShipmentService::class);
        $nativeShipments->method('getQuantities')->willReturn([]);
        $nativeShipments->method('getQuantitiesLeft')->willReturn($left);
        $nativeShipments->method('getShipmentQuantities')->willReturn($inShipment);

        return $this->getMockBuilder(AutoPacker::class)->setConstructorArgs([
            $this->createStub(OrderAmountCalculator::class),
            $this->createStub(ItemDimensionsProvider::class),
            $this->createStub(PackTemplateRepositoryInterface::class),
            $this->createStub(PackFactory::class),
            $this->createStub(ItemFactory::class),
            $this->createStub(ConfigProvider::class),
            $nativeShipments,
        ])->onlyMethods(['pack'])->getMock();
    }

    private function testConnection(
        callable $answer,
        string $savedLogin = '12345',
        string $savedToken = 'saved-token',
    ): TestConnection {
        $utils = $this->createStub(UtilsServiceInterface::class);
        $utils->method('testAuthApi')->willReturnCallback($answer);
        $client = $this->createStub(PolkurierClient::class);
        $client->method('getUtils')->willReturn($utils);
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getLogin')->willReturn($savedLogin);
        $config->method('getToken')->willReturn($savedToken);
        return new TestConnection(
            $this->createContext(),
            $this->createJsonFactory(),
            $client,
            $config,
            new ErrorMessageResolver(),
            NewingObjectManager::createFactory(AuthorizationMessageFactory::class),
        );
    }

    /**
     * @param string[] $waybills
     */
    private function createShipment(
        string $state,
        ?string $number,
        array $waybills,
        ?string $error = null,
    ): ShipmentInterface
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
