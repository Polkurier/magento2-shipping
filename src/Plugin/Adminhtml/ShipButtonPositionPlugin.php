<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Item;
use Magento\Backend\Block\Widget\Button\Toolbar;
use Magento\Framework\View\Element\AbstractBlock;

/**
 * Puts the order page's "Ship with Polkurier" button right after Magento's "Ship", or where "Ship"
 * would be when the order has nothing left to ship.
 *
 * Magento gives the order page's buttons no order of their own (they share one sort order and keep
 * the order they were added in), and some come in late: with Inventory, "Ship" is reworked by a
 * block of its own. The blocks of the page share the button list, and each lays it out again, so
 * the buttons are put in order every time, as the toolbar lays them out.
 */
readonly class ShipButtonPositionPlugin
{
    public const string BUTTON = 'polkurier_ship';

    /**
     * The buttons "Ship" comes after on the order page, nearest first: the button follows the first
     * one there is, or comes last.
     */
    private const array ANCHORS = ['order_ship', 'order_invoice', 'order_unhold', 'order_hold', 'send_notification', 'order_cancel'];

    /**
     * Leaves the arguments as they are.
     */
    public function beforePushButtons(Toolbar $subject, AbstractBlock $context, ButtonList $buttonList): null
    {
        foreach ($buttonList->getItems() as $items) {
            if (isset($items[self::BUTTON])) {
                $this->order($items);
            }
        }

        return null;
    }

    /**
     * Numbers the buttons of one level in the order they are in, with this one after its anchor.
     *
     * @param array<string, Item> $items sorted
     */
    private function order(array $items): void
    {
        $button = $items[self::BUTTON];
        unset($items[self::BUTTON]);
        $anchor = null;
        foreach (self::ANCHORS as $id) {
            if (isset($items[$id])) {
                $anchor = $id;
                break;
            }
        }
        $position = 0;
        foreach ($items as $id => $item) {
            $item->setData('sort_order', $position += 10);
            if ($id === $anchor) {
                $button->setData('sort_order', $position += 10);
            }
        }
        if ($anchor === null) {
            $button->setData('sort_order', $position + 10);
        }
    }
}
