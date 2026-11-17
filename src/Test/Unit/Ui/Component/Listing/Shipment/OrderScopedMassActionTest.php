<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Listing\Shipment;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Ui\Component\Listing\Shipment\OrderScopedMassAction;

#[CoversClass(OrderScopedMassAction::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderScopedMassActionTest extends TestCase
{
    public function testOnAnOrdersTabTheActionAndItsSubmenuGoToThatOrdersShipmentsOnly(): void
    {
        $config = $this->prepare(15, [
            'url' => 'https://shop.example/admin/polkurier/shipment/label/',
            'actions' => [['type' => 'pdf', 'url' => 'https://shop.example/admin/polkurier/shipment/label/format/PDF']],
        ]);

        self::assertSame('https://shop.example/admin/polkurier/shipment/label/order_id/15/', $config['url']);
        self::assertSame(
            'https://shop.example/admin/polkurier/shipment/label/format/PDF/order_id/15/',
            $config['actions'][0]['url'],
        );
    }

    public function testOnTheShipmentGridTheActionIsLeftAsItIs(): void
    {
        $url = 'https://shop.example/admin/polkurier/shipment/label/';

        self::assertSame($url, $this->prepare(0, ['url' => $url])['url']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed> the config after preparing
     */
    private function prepare(int $orderId, array $config): array
    {
        $context = $this->createStub(ContextInterface::class);
        $context->method('getProcessor')->willReturn($this->createStub(Processor::class));
        $context->method('getRequestParam')->willReturn($orderId ?: null);
        $action = new OrderScopedMassAction(
            $context,
            $this->createStub(AuthorizationInterface::class),
            [],
            ['config' => $config],
        );

        $action->prepare();

        return (array)$action->getData('config');
    }
}
