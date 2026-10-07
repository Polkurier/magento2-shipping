<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Plugin\Adminhtml\ShipmentSavePlugin;

#[CoversClass(ShipmentSavePlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentSavePluginTest extends TestCase
{
    /** @var string[] */
    private array $errors = [];
    /** @var array{0: string, 1: array<string, mixed>}|null */
    private ?array $redirect = null;

    public function testWithTheLabelMagentoGoesOn(): void
    {
        self::assertSame('saved', $this->save(['create_shipping_label' => '1'], 'polkurier_inpost_paczkomat'));
    }

    public function testWithoutItAPolkurierOrderIsntShipped(): void
    {
        self::assertNotSame('saved', $this->save([], 'polkurier_inpost_paczkomat'));
        self::assertSame(['adminhtml/order_shipment/new', ['order_id' => 9]], $this->redirect);
        self::assertStringStartsWith(
            'Order #000000009 ships with a Polkurier order, so its shipment is created with a shipping label',
            $this->errors[0],
        );
    }

    public function testWithMagentosLabelsOffItPointsToTheShipmentScreen(): void
    {
        self::assertNotSame('saved', $this->save([], 'polkurier_inpost_paczkomat', labels: false));
        self::assertStringContainsString('use Ship on the order page', $this->errors[0]);
    }

    public function testAnOrderOfAnotherCarrierIsLeftAlone(): void
    {
        self::assertSame('saved', $this->save([], 'flatrate_flatrate'));
    }

    public function testNothingIsStoppedWhenTheModuleShipsNothingItself(): void
    {
        self::assertSame('saved', $this->save([], 'polkurier_inpost_paczkomat', labels: false, screen: false));
    }

    /**
     * @param array<string, mixed> $shipment the form's shipment fields
     */
    private function save(array $shipment, string $method, bool $labels = true, bool $screen = true): mixed
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['shipment', [], $shipment], ['order_id', null, '9']]);
        $controller = $this->createStub(Save::class);
        $controller->method('getRequest')->willReturn($request);

        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn($method);
        $order->method('getIncrementId')->willReturn('000000009');
        $order->method('getStoreId')->willReturn(1);
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('get')->willReturn($order);

        $config = $this->createStub(ConfigProvider::class);
        $config->method('isNativeLabelsEnabled')->willReturn($labels);
        $config->method('isShipmentScreenEnabled')->willReturn($screen);

        $messages = $this->createStub(ManagerInterface::class);
        $messages->method('addErrorMessage')->willReturnCallback(function ($message) use ($messages) {
            $this->errors[] = (string)$message;

            return $messages;
        });
        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function (string $path, array $params = []) use ($redirect) {
            $this->redirect = [$path, $params];

            return $redirect;
        });
        $redirects = $this->createStub(RedirectFactory::class);
        $redirects->method('create')->willReturn($redirect);

        return (new ShipmentSavePlugin(
            new OrderLoader($orders),
            $config,
            $messages,
            $redirects,
            $this->createStub(NativeLabelService::class),
        ))
            ->aroundExecute($controller, static fn(): string => 'saved');
    }
}
