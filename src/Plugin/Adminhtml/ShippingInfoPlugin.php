<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Sales\Block\Adminhtml\Order\AbstractOrder;
use Magento\Shipping\Block\Adminhtml\View\Form as ShipmentForm;

/**
 * Adds Polkurier's part to the shipping information under the order's shipping method, which
 * stays as the customer's choice. Magento's templates render no children, so the children
 * declared in the layout are put in by hand:
 *  - the order page's Shipping & Handling Information gets the customer's pickup point
 *    (polkurier_order_point) and the carriers the parcels went with (polkurier_shipped_with),
 *    before the closing tag of the section's content;
 *  - the shipment page's Shipping and Tracking Information gets the carrier that shipment went
 *    with (polkurier_shipped_with), right under the shipping method. The Polkurier label options
 *    (polkurier_label_options) and the shipment's Polkurier orders (polkurier_shipment_orders) go
 *    above Payment & Shipping Method.
 */
readonly class ShippingInfoPlugin
{
    private const string ORDER_BLOCK = 'order_shipping_view';
    private const string SHIPMENT_BLOCK = 'form';
    private const string POINT = 'polkurier_order_point';
    private const string SHIPPED_WITH = 'polkurier_shipped_with';
    private const string SHIPMENT_METHOD = '<div class="shipping-description-title">';
    private const string LABEL_OPTIONS = 'polkurier_label_options';
    private const string SHIPMENT_ORDERS = 'polkurier_shipment_orders';
    private const string PAYMENT_AND_SHIPPING = '<section class="admin__page-section order-shipment-billing-shipping">';

    public function afterToHtml(AbstractOrder $subject, string $html): string
    {
        if ($html === '') {
            return $html;
        }
        if ($subject->getNameInLayout() === self::ORDER_BLOCK) {
            return $this->insertIntoOrderInfo($subject, $html);
        }
        if ($subject instanceof ShipmentForm && $subject->getNameInLayout() === self::SHIPMENT_BLOCK) {
            return $this->insertIntoShipmentInfo($subject, $html);
        }

        return $html;
    }

    private function insertIntoOrderInfo(AbstractOrder $subject, string $html): string
    {
        $added = $this->renderChild($subject, self::POINT) . $this->renderChild($subject, self::SHIPPED_WITH);
        if (\trim($added) === '') {
            return $html;
        }
        // The last </div> closes the section, the one before it the section's content.
        $section = \strrpos($html, '</div>');
        $content = $section === false ? false : \strrpos(\substr($html, 0, $section), '</div>');

        return $content === false ? $html . $added : \substr_replace($html, $added, $content, 0);
    }

    private function insertIntoShipmentInfo(ShipmentForm $subject, string $html): string
    {
        $shipmentId = (int)$subject->getShipment()?->getId();
        $added = $shipmentId ? $this->renderChild($subject, self::SHIPPED_WITH, ['shipment_id' => $shipmentId]) : '';
        $method = \strpos($html, self::SHIPMENT_METHOD);
        $end = $method === false ? false : \strpos($html, '</div>', $method);
        if (\trim($added) !== '' && $end !== false) {
            $html = \substr_replace($html, $added, $end + \strlen('</div>'), 0);
        }
        // The label options and the shipment's Polkurier orders, above Payment & Shipping Method,
        // or at the end when Magento's template has no such section.
        $sections = $this->renderChild($subject, self::LABEL_OPTIONS)
            . $this->renderChild($subject, self::SHIPMENT_ORDERS);
        if (\trim($sections) === '') {
            return $html;
        }
        $payment = \strpos($html, self::PAYMENT_AND_SHIPPING);

        return $payment === false ? $html . $sections : \substr_replace($html, $sections, $payment, 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderChild(AbstractOrder $subject, string $name, array $data = []): string
    {
        $child = $subject->getChildBlock($name);
        if (!$child instanceof AbstractBlock) {
            return '';
        }

        return $child->addData(['order' => $subject->getOrder()] + $data)->toHtml();
    }
}
