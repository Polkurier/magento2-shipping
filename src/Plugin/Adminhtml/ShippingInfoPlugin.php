<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Sales\Block\Adminhtml\Order\AbstractOrder;

/**
 * Adds the customer's pickup point to the order page's Shipping & Handling Information, under the
 * shipping method. Magento's template renders no children, so the point (the polkurier_order_point
 * child in sales_order_view.xml) goes in before the closing tag of the section's content.
 */
class ShippingInfoPlugin
{
    private const string BLOCK = 'order_shipping_view';
    private const string POINT = 'polkurier_order_point';

    public function afterToHtml(AbstractOrder $subject, string $html): string
    {
        if ($html === '' || $subject->getNameInLayout() !== self::BLOCK) {
            return $html;
        }
        $point = $subject->getChildBlock(self::POINT);
        if (!$point instanceof \Magento\Framework\View\Element\AbstractBlock) {
            return $html;
        }
        $pointHtml = $point->setData('order', $subject->getOrder())->toHtml();
        if (\trim($pointHtml) === '') {
            return $html;
        }
        // The last </div> closes the section, the one before it the section's content.
        $section = \strrpos($html, '</div>');
        $content = $section === false ? false : \strrpos(\substr($html, 0, $section), '</div>');

        return $content === false ? $html . $pointHtml : \substr_replace($html, $pointHtml, $content, 0);
    }
}
