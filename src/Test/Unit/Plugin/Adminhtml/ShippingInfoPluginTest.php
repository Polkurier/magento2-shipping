<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Block\Adminhtml\Order\AbstractOrder;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Plugin\Adminhtml\ShippingInfoPlugin;

#[CoversClass(ShippingInfoPlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class ShippingInfoPluginTest extends TestCase
{
    private const string SECTION = "<div class=\"order-shipping-method\">\n    <div class=\"admin__page-section-item-content\">\n        <strong>Paczkomat</strong> PLN 14.99\n    </div>\n</div>\n";

    public function testThePointGoesUnderTheShippingMethod(): void
    {
        $html = (new ShippingInfoPlugin())->afterToHtml($this->block('order_shipping_view', '<p>WAW195M</p>'), self::SECTION);

        self::assertSame("<div class=\"order-shipping-method\">\n    <div class=\"admin__page-section-item-content\">\n        <strong>Paczkomat</strong> PLN 14.99\n    <p>WAW195M</p></div>\n</div>\n", $html);
    }

    public function testWithoutAPointTheSectionStaysAsItIs(): void
    {
        self::assertSame(self::SECTION, (new ShippingInfoPlugin())->afterToHtml($this->block('order_shipping_view', "\n"), self::SECTION));
    }

    public function testOtherOrderBlocksAreLeftAlone(): void
    {
        self::assertSame(self::SECTION, (new ShippingInfoPlugin())->afterToHtml($this->block('order_payment', '<p>WAW195M</p>'), self::SECTION));
    }

    private function block(string $name, string $pointHtml): AbstractOrder
    {
        $point = $this->createStub(Template::class);
        $point->method('setData')->willReturnSelf();
        $point->method('toHtml')->willReturn($pointHtml);
        $block = $this->createStub(AbstractOrder::class);
        $block->method('getNameInLayout')->willReturn($name);
        $block->method('getChildBlock')->willReturn($point);
        $block->method('getOrder')->willReturn($this->createStub(Order::class));

        return $block;
    }
}
