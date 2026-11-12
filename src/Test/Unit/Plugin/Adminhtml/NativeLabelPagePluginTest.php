<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\CreateLabel;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Admin\FlashMessageCollector;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelPackageMapper;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\ManualEntrySaver;
use Polkurier\Shipping\Plugin\Adminhtml\NativeLabelPagePlugin;

#[CoversClass(NativeLabelPagePlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class NativeLabelPagePluginTest extends TestCase
{
    private ?FlashMessageCollector $flashMessageCollector = null;

    /** @var array<string, mixed> the request's params */
    private array $params = [];

    /** @var string[] */
    private array $warnings = [];

    /** @var string[] */
    private array $successes = [];

    /** @var array<string, mixed>|null what was answered in JSON instead of going on */
    private ?array $json = null;

    public function testWithoutTheRightToPlaceOrdersNoLabelIsAskedFor(): void
    {
        $this->params = ['order_id' => '9', 'shipment' => ['create_shipping_label' => '1']];

        self::assertNotSame('proceeded', $this->execute(Save::class, allowed: []));
        self::assertSame(['error' => true, 'message' => 'You are not allowed to place Polkurier orders.'], $this->json);
    }

    public function testALabelForAShipmentMadeWithoutFindsItsOrder(): void
    {
        $this->params = ['shipment_id' => '4'];

        self::assertNotSame('proceeded', $this->execute(CreateLabel::class, allowed: []));
        self::assertNotNull($this->json);
    }

    public function testWithoutTheRightToChangePackTemplatesNoneIsKept(): void
    {
        $this->params = [
            'order_id' => '9',
            'shipment' => ['create_shipping_label' => '1'],
            'packages' => [
                1 => ['params' => ['container' => 'ST', NativeLabelPackageMapper::KEEP_AS_PARAM => 'Tuba']],
                2 => ['params' => ['container' => 'ST']],
            ],
        ];

        self::assertSame('proceeded', $this->execute(Save::class, allowed: ['Polkurier_Shipping::shipment_create']));
        self::assertSame(
            [1 => ['params' => ['container' => 'ST']], 2 => ['params' => ['container' => 'ST']]],
            $this->params['packages'],
        );
        self::assertSame(
            ['"Tuba" wasn\'t saved to pack templates: you aren\'t allowed to change them.'],
            $this->warnings,
        );
    }

    public function testWithBothRightsThePackagesStayAsTheyWere(): void
    {
        $packages = [1 => ['params' => [NativeLabelPackageMapper::KEEP_AS_PARAM => 'Tuba']]];
        $this->params = ['order_id' => '9', 'shipment' => ['create_shipping_label' => '1'], 'packages' => $packages];

        self::assertSame(
            'proceeded',
            $this->execute(
                Save::class,
                allowed: ['Polkurier_Shipping::shipment_create', 'Polkurier_Shipping::pack_templates'],
            ),
        );
        self::assertSame($packages, $this->params['packages']);
        self::assertSame([], $this->warnings);
    }

    public function testWhatBecameOfTheEntriesKeptIsSaidAfterTheLabel(): void
    {
        $this->params = ['order_id' => '9', 'shipment' => ['create_shipping_label' => '1']];
        $this->flashMessageCollector = (new FlashMessageCollector())->addSuccess('"Tuba" was saved to pack templates.');

        $this->execute(
            Save::class,
            allowed: ['Polkurier_Shipping::shipment_create', 'Polkurier_Shipping::pack_templates'],
        );

        self::assertSame(['"Tuba" was saved to pack templates.'], $this->successes);
    }

    public function testTheOptionsPostedGoOnTheMagentoShipment(): void
    {
        $this->params = [NativeLabelService::OPTIONS_PARAM => ['carrier_code' => 'DPD']];
        $request = $this->request();
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getOrder')->willReturn($this->createOrder('polkurier_inpost_paczkomat'));
        $shipment->expects(self::once())
            ->method('setData')
            ->with(NativeLabelService::FIELD_OPTIONS, ['carrier_code' => 'DPD']);

        $this->createPlugin([])->beforeCreate($this->createStub(LabelGenerator::class), $shipment, $request);
    }

    public function testAShipmentWithoutALabelIsLeftAlone(): void
    {
        $this->params = ['order_id' => '9', 'shipment' => []];

        self::assertSame('proceeded', $this->execute(Save::class, allowed: []));
    }

    public function testAnOrderOfAnotherCarrierIsLeftAlone(): void
    {
        $this->params = ['order_id' => '9', 'shipment' => ['create_shipping_label' => '1']];

        self::assertSame('proceeded', $this->execute(Save::class, allowed: [], method: 'flatrate_flatrate'));
    }

    /**
     * @param class-string<Action> $action
     * @param string[] $allowed the ACL resources the admin has
     */
    private function execute(string $action, array $allowed, string $method = 'polkurier_inpost_paczkomat'): mixed
    {
        $controller = $this->createStub($action);
        $controller->method('getRequest')->willReturn($this->request());

        return $this->createPlugin($allowed, $method)->aroundExecute($controller, static fn(): string => 'proceeded');
    }

    /**
     * @param string[] $allowed the ACL resources the admin has
     */
    private function createPlugin(array $allowed, string $method = 'polkurier_inpost_paczkomat'): NativeLabelPagePlugin
    {
        $order = $this->createOrder($method);
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('get')
            ->willReturnCallback(
                static fn(int $id): Order => $id === 9 ? $order : throw new \LogicException('Order ' . $id),
            );
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getOrderId')->willReturn(9);
        $shipments = $this->createStub(ShipmentRepositoryInterface::class);
        $shipments->method('get')->willReturn($shipment);
        $saver = $this->createStub(ManualEntrySaver::class);
        $saver->method('takeOutcome')->willReturnCallback(
            fn(): FlashMessageCollector => $this->flashMessageCollector ?? new FlashMessageCollector(),
        );

        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')
            ->willReturnCallback(static fn(string $resource): bool => \in_array($resource, $allowed, true));
        $messages = $this->createStub(ManagerInterface::class);
        $messages->method('addWarningMessage')
            ->willReturnCallback(function ($message) use ($messages): ManagerInterface {
                $this->warnings[] = (string)$message;

                return $messages;
            });
        $messages->method('addSuccessMessage')
            ->willReturnCallback(function ($message) use ($messages): ManagerInterface {
                $this->successes[] = (string)$message;

                return $messages;
            });
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function (array $data) use ($json): Json {
            $this->json = $data;

            return $json;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        return new NativeLabelPagePlugin(
            new OrderLoader($orders),
            $saver,
            $shipments,
            $authorization,
            $messages,
            $jsonFactory,
        );
    }

    private function request(): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')
            ->willReturnCallback(fn(string $name, mixed $default = null): mixed => $this->params[$name] ?? $default);
        $request->method('setParams')->willReturnCallback(function (array $params) use ($request): RequestInterface {
            $this->params = $params + $this->params;

            return $request;
        });

        return $request;
    }

    private function createOrder(string $method): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn($method);
        $order->method('getStoreId')->willReturn(1);

        return $order;
    }
}
