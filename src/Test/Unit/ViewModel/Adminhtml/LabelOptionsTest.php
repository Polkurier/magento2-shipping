<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\ViewModel\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Enum\Config\ParcelWeightEnum;
use Polkurier\Shipping\Model\Admin\Formatter;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PickupMode;
use Polkurier\Shipping\Model\PickupPoint\AdminMapConfigProvider;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Screen\ScreenDataProvider;
use Polkurier\Shipping\Model\Shipment\Screen\ShipmentOptionsProvider;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Shipping\ViewModel\Adminhtml\LabelOptions;

#[CoversClass(LabelOptions::class)]
#[AllowMockObjectsWithoutExpectations]
class LabelOptionsTest extends TestCase
{
    private string $shippingMethod = 'polkurier_DPD';
    private bool $allowed = true;

    /**
     * @var array<string, int> the page's parameters
     */
    private array $params = ['order_id' => 15];

    private ?MagentoShipmentModel $existing = null;

    /**
     * @var array<int, mixed> the order's Magento shipments that can take a Polkurier order, by id
     */
    private array $free = [];

    public function testANewShipmentOfAPolkurierOrderGetsTheOptionsWhenTheAdminMayPlaceOrders(): void
    {
        self::assertTrue($this->createViewModel()->isAvailable());

        $this->allowed = false;
        self::assertFalse($this->createViewModel()->isAvailable());
    }

    public function testAnotherCarriersOrderGetsNoOptions(): void
    {
        $this->shippingMethod = 'flatrate_flatrate';

        $options = $this->createViewModel();

        self::assertFalse($options->isPolkurierOrder());
        self::assertFalse($options->isAvailable());
    }

    public function testAnExistingShipmentGetsThemOnlyWithoutALabelAndFreeToTakeAnOrder(): void
    {
        $this->params = ['shipment_id' => 7];
        $this->existing = $this->createShipment(null);
        self::assertFalse($this->createViewModel()->isAvailable(), 'Another Polkurier order holds it.');

        $this->free = [7 => true];
        self::assertTrue($this->createViewModel()->isAvailable());
        self::assertTrue($this->createViewModel()->isForExistingShipment());

        $this->existing = $this->createShipment('%PDF');
        self::assertFalse($this->createViewModel()->isAvailable(), 'Its label exists already.');
    }

    public function testThePageIsGivenOnlyTheOptionFieldsOfTheDefaultDraft(): void
    {
        $config = (array)\json_decode($this->createViewModel()->getJsConfig(), true);

        self::assertSame(NativeLabelService::OPTIONS_PARAM, $config['name']);
        self::assertFalse($config['existingShipment']);
        self::assertSame(['carrier_code' => 'DPD', 'description' => '#000000031'], $config['values']);
        self::assertSame(['baseUrl' => 'https://maps.example/'], $config['map']);
    }

    public function testWithoutTheOptionsNothingIsPackedAutomatically(): void
    {
        $this->allowed = false;

        $config = (array)\json_decode($this->createViewModel()->getPackagingConfig(), true);

        self::assertNull($config['autoPack']);
        self::assertNull($this->createViewModel()->getFailedAttempt(), 'No attempt waits to be sent again.');
    }

    private function createShipment(?string $label): MagentoShipmentModel
    {
        $shipment = $this->createStub(MagentoShipmentModel::class);
        $shipment->method('getId')->willReturn(7);
        $shipment->method('getOrderId')->willReturn(15);
        $shipment->method('getShippingLabel')->willReturn($label);

        return $shipment;
    }

    private function createViewModel(): LabelOptions
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn(string $name): ?int => $this->params[$name] ?? null);
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn($this->allowed);
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn(15);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getShippingMethod')->willReturn($this->shippingMethod);
        $orders = $this->createStub(OrderLoader::class);
        $orders->method('find')->willReturn($order);
        $screen = $this->createStub(ScreenDataProvider::class);
        $screen->method('getDefaultValues')->willReturn([
            'carrier_code' => 'DPD',
            'description' => '#000000031',
            'recipient_person' => 'Not an option of the label',
        ]);
        $screen->method('getAmounts')->willReturn(\array_fill_keys(
            ['grand_total', 'shipping_charged', 'cod_collected', 'cod_remaining', 'shipping_covered'],
            0.0,
        ));
        $screen->method('describeRecipientPoint')->willReturn(['id' => '', 'closed' => false]);
        $screen->method('describePoints')->willReturn([]);
        $screen->method('getTemplates')->willReturn([]);
        $shipments = $this->createStub(ShipmentRepositoryInterface::class);
        $shipments->method('get')->willReturnCallback(fn(): ?MagentoShipmentModel => $this->existing);
        $native = $this->createStub(NativeShipmentService::class);
        $native->method('getFreeShipments')->willReturnCallback(fn(): array => $this->free);
        $native->method('getShipmentQuantities')->willReturn([]);
        $settings = $this->createStub(ConfigProvider::class);
        $settings->method('getParcelWeight')->willReturn(ParcelWeightEnum::Products);
        $maps = $this->createStub(AdminMapConfigProvider::class);
        $maps->method('getMapConfig')->willReturn(['baseUrl' => 'https://maps.example/']);

        return new LabelOptions(
            $request,
            $authorization,
            $orders,
            $screen,
            $this->createStub(ShipmentOptionsProvider::class),
            $settings,
            $this->createStub(AdditionalService::class),
            $this->createStub(CodType::class),
            $this->createStub(CodReturnType::class),
            $this->createStub(PickupMode::class),
            new Json(),
            $this->createStub(OrderAmountCalculator::class),
            $shipments,
            $native,
            $this->createStub(RetriableShipmentFinder::class),
            $this->createStub(FailedShipmentManager::class),
            $this->createStub(UrlInterface::class),
            $maps,
            $this->createStub(Formatter::class),
        );
    }
}
