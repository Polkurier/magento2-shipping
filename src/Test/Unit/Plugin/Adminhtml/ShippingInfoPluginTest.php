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
use Magento\Sales\Model\Order\Shipment;
use Magento\Shipping\Block\Adminhtml\View\Form;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Plugin\Adminhtml\ShippingInfoPlugin;

#[CoversClass(ShippingInfoPlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class ShippingInfoPluginTest extends TestCase
{
    private const string SECTION = "<div class=\"order-shipping-method\">\n    <div class=\"admin__page-section-item-content\">\n        <strong>Paczkomat</strong> PLN 14.99\n    </div>\n</div>\n";

    public function testThePointAndTheCarrierShippedWithGoUnderTheShippingMethod(): void
    {
        $html = (new ShippingInfoPlugin())->afterToHtml(
            $this->block(
                'order_shipping_view',
                ['polkurier_order_point' => '<p>WAW195M</p>', 'polkurier_shipped_with' => '<p>DPD</p>'],
            ),
            self::SECTION,
        );

        self::assertSame(
            "<div class=\"order-shipping-method\">\n    <div class=\"admin__page-section-item-content\">\n        <strong>Paczkomat</strong> PLN 14.99\n    <p>WAW195M</p><p>DPD</p></div>\n</div>\n",
            $html,
        );
    }

    public function testWithNothingToAddTheSectionStaysAsItIs(): void
    {
        self::assertSame(self::SECTION, (new ShippingInfoPlugin())->afterToHtml(
            $this->block('order_shipping_view', ['polkurier_order_point' => "\n", 'polkurier_shipped_with' => '']),
            self::SECTION,
        ));
    }

    public function testOtherOrderBlocksAreLeftAlone(): void
    {
        self::assertSame(self::SECTION, (new ShippingInfoPlugin())->afterToHtml(
            $this->block('order_payment', ['polkurier_order_point' => '<p>WAW195M</p>']),
            self::SECTION,
        ));
    }

    public function testTheShipmentPageGetsItsOwnCarrierRightUnderTheShippingMethod(): void
    {
        $magentoShipment = $this->createStub(Shipment::class);
        $magentoShipment->method('getId')->willReturn(10);
        $shippedWith = $this->child('<p>InPost</p>', $data);
        $form = $this->createStub(Form::class);
        $form->method('getNameInLayout')->willReturn('form');
        $form->method('getShipment')->willReturn($magentoShipment);
        $form->method('getOrder')->willReturn($this->createStub(Order::class));
        $form->method('getChildBlock')->willReturnMap([['polkurier_shipped_with', $shippedWith]]);

        $html = (new ShippingInfoPlugin())->afterToHtml(
            $form,
            '<div class="shipping-description-wrapper"><div '
            . 'class="shipping-description-title">DHL POP</div>Total Shipping Charges</div>',
        );

        self::assertSame(
            '<div class="shipping-description-wrapper"><div class="shipping-description-title">DHL '
            . 'POP</div><p>InPost</p>Total Shipping Charges</div>',
            $html,
        );
        self::assertSame(10, $data['shipment_id'] ?? null, 'Only that shipment\'s carrier.');
    }

    /**
     * @param array<string, string> $children HTML by child name
     */
    private function block(string $name, array $children): AbstractOrder
    {
        $block = $this->createStub(AbstractOrder::class);
        $block->method('getNameInLayout')->willReturn($name);
        $block->method('getChildBlock')->willReturnCallback(
            fn(string $child): ?Template => isset($children[$child]) ? $this->child($children[$child]) : null,
        );
        $block->method('getOrder')->willReturn($this->createStub(Order::class));

        return $block;
    }

    /**
     * @param array<string, mixed>|null $data receives what the child is given
     */
    private function child(string $html, ?array &$data = null): Template
    {
        $child = $this->createStub(Template::class);
        $child->method('addData')->willReturnCallback(function (array $given) use ($child, &$data) {
            $data = $given;

            return $child;
        });
        $child->method('toHtml')->willReturn($html);

        return $child;
    }
}
