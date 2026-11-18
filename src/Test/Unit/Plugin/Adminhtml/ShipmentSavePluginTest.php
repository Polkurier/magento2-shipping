<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\Collection;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Plugin\Adminhtml\ShipmentSavePlugin;

#[CoversClass(ShipmentSavePlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentSavePluginTest extends TestCase
{
    private const array LABEL = ['create_shipping_label' => '1'];

    /** @var string[] the messages left, in order */
    private array $messages = [];

    /** @var array{0: string, 1: array<string, mixed>}|null */
    private ?array $redirect = null;

    public function testASaveWithoutAnUnreadyLabelKeepsMagentosMessage(): void
    {
        self::assertSame('saved', $this->save(self::LABEL));
        self::assertSame(['The shipment has been created.'], $this->messages);
    }

    public function testAnUnreadyLabelReplacesMagentosMessage(): void
    {
        self::assertSame('saved', $this->save(self::LABEL, unready: 'PK123'));
        self::assertCount(2, $this->messages);
        self::assertSame('Other', $this->messages[0]);
        self::assertStringStartsWith('The shipment has been created with Polkurier order PK123', $this->messages[1]);
    }

    public function testAShipmentWithoutALabelIsSavedWhenNothingIsPending(): void
    {
        $shipment = $this->createPolkurierShipment(ShipmentInterface::STATE_CREATED, 56);

        self::assertSame('saved', $this->save([], [$shipment]));
    }

    public function testAShipmentWithoutALabelWaitsForAnUnconfirmedPolkurierOrder(): void
    {
        $shipment = $this->createPolkurierShipment(ShipmentInterface::STATE_UNKNOWN);

        self::assertNotSame('saved', $this->save([], [$shipment]));
        self::assertSame(['adminhtml/order_shipment/new', ['order_id' => 9]], $this->redirect);
        self::assertCount(1, $this->messages);
        self::assertStringStartsWith(
            'Order #000000009 has a Polkurier order that Polkurier hasn\'t confirmed',
            $this->messages[0],
        );
    }

    public function testAShipmentWithoutALabelWaitsForAPolkurierOrderBeingPlaced(): void
    {
        $shipment = $this->createPolkurierShipment(ShipmentInterface::STATE_PENDING);

        self::assertNotSame('saved', $this->save([], [$shipment]));
        self::assertStringEndsWith('Nothing was shipped.', $this->messages[0]);
    }

    public function testAShipmentWithoutALabelWaitsForThePlacedOrdersMagentoShipment(): void
    {
        self::assertNotSame('saved', $this->save([], [
            $this->createPolkurierShipment(ShipmentInterface::STATE_CREATED, 56),
            $this->createPolkurierShipment(ShipmentInterface::STATE_CREATED),
        ]));
        self::assertSame(['adminhtml/order_shipment/new', ['order_id' => 9]], $this->redirect);
        self::assertStringStartsWith(
            'Order #000000009 has Polkurier order PK-2, whose Magento shipment isn\'t created yet.',
            $this->messages[0],
        );
    }

    public function testAPolkurierOrderForAnExistingMagentoShipmentTakesNoItems(): void
    {
        self::assertSame(
            'saved',
            $this->save([], [$this->createPolkurierShipment(ShipmentInterface::STATE_UNKNOWN, target: 56)]),
        );
    }

    public function testAnOrderOfAnotherCarrierIsLeftAlone(): void
    {
        self::assertSame(
            'saved',
            $this->save([], [$this->createPolkurierShipment(ShipmentInterface::STATE_UNKNOWN)], 'flatrate'),
        );
    }

    /**
     * @param array<string, mixed> $shipment the form's shipment fields
     * @param ShipmentInterface[] $polkurier the order's live Polkurier shipments
     */
    private function save(
        array $shipment,
        array $polkurier = [],
        string $method = 'polkurier_DPD',
        ?string $unready = null,
    ): mixed {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['shipment', [], $shipment], ['order_id', null, '9']]);
        $controller = $this->createStub(Save::class);
        $controller->method('getRequest')->willReturn($request);

        $order = $this->createStub(Order::class);
        $order->method('getShippingMethod')->willReturn($method);
        $order->method('getIncrementId')->willReturn('000000009');
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('get')->willReturn($order);
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findForOrder')->willReturn($polkurier);

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function (string $path, array $params = []) use ($redirect) {
            $this->redirect = [$path, $params];

            return $redirect;
        });
        $redirects = $this->createStub(RedirectFactory::class);
        $redirects->method('create')->willReturn($redirect);

        $labels = $this->createStub(NativeLabelService::class);
        $labels->method('takeUnreadyLabel')->willReturn($unready);
        $proceed = function () use ($unready): string {
            $this->messages = $unready === null
                ? ['The shipment has been created.']
                : ['The shipment has been created. You created the shipping label.', 'Other'];

            return 'saved';
        };

        $plugin = new ShipmentSavePlugin(
            new OrderLoader($orders),
            $finder,
            $this->createMessageManager(),
            $redirects,
            $labels,
        );

        return $plugin->aroundExecute($controller, $proceed);
    }

    /**
     * Holds $this->messages; Magento's own message is added by its action.
     */
    private function createMessageManager(): ManagerInterface
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getItems')->willReturnCallback(function (): array {
            $taken = [];
            foreach ($this->messages as $text) {
                $message = $this->createStub(MessageInterface::class);
                $message->method('getText')->willReturn($text);
                $taken[] = $message;
            }
            $this->messages = [];

            return $taken;
        });
        $manager = $this->createStub(ManagerInterface::class);
        $manager->method('getMessages')->willReturn($collection);
        $manager->method('addMessage')->willReturnCallback(function (MessageInterface $message) use ($manager) {
            $this->messages[] = (string)$message->getText();

            return $manager;
        });
        $add = function ($message) use ($manager) {
            $this->messages[] = (string)$message;

            return $manager;
        };
        $manager->method('addNoticeMessage')->willReturnCallback($add);
        $manager->method('addErrorMessage')->willReturnCallback($add);

        return $manager;
    }

    private function createPolkurierShipment(
        string $state,
        ?int $magentoShipmentId = null,
        ?int $target = null,
    ): Shipment {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class, ['json' => new Json()]);
        $placed = $state === ShipmentInterface::STATE_CREATED;

        return $shipment->setState($state)
            ->setPolkurierNumber($placed ? ($magentoShipmentId !== null ? 'PK-1' : 'PK-2') : null)
            ->setShipmentId($magentoShipmentId)
            ->setTargetShipmentId($target);
    }
}
