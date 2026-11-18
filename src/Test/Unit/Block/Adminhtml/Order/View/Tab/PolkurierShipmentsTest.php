<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Block\Adminhtml\Order\View\Tab;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Registry;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Block\Adminhtml\Order\View\Tab\PolkurierShipments;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Index as ShipmentIndex;

#[CoversClass(PolkurierShipments::class)]
#[AllowMockObjectsWithoutExpectations]
class PolkurierShipmentsTest extends TestCase
{
    public function testAPolkurierOrderShowsTheTabToThoseWhoMaySeeShipments(): void
    {
        self::assertTrue($this->createTab('polkurier_dpd', true)->canShowTab());
        self::assertFalse($this->createTab('polkurier_dpd', false)->canShowTab());
    }

    public function testAnOrderOfAnotherCarrierHasNoTab(): void
    {
        self::assertFalse($this->createTab('flatrate_flatrate', true)->canShowTab());
        self::assertFalse($this->createTab(null, true)->canShowTab(), 'Nor does a page without an order.');
    }

    private function createTab(?string $shippingMethod, bool $allowed): PolkurierShipments
    {
        $registry = $this->createStub(Registry::class);
        if ($shippingMethod !== null) {
            $order = $this->createStub(Order::class);
            $order->method('getShippingMethod')->willReturn($shippingMethod);
            $registry->method('registry')->willReturnMap([['current_order', $order]]);
        }
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnMap([[ShipmentIndex::ADMIN_RESOURCE, null, $allowed]]);
        /** @var PolkurierShipments $tab */
        $tab = (new ObjectManager($this))->getObject(PolkurierShipments::class, [
            'registry' => $registry,
            'authorization' => $authorization,
        ]);

        return $tab;
    }
}
