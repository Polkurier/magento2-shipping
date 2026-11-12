<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Item;
use Magento\Backend\Block\Widget\Button\Toolbar;
use Magento\Framework\View\Element\AbstractBlock;

/**
 * Puts the order page's "Ship with Polkurier" button right after Magento's "Ship", or last when the
 * page has no "Ship": everything is shipped, or the admin may not ship.
 *
 * Magento doesn't sort the order page's buttons: they share one sort order and keep the order they
 * were added in. Some are added late: with Inventory, a block of its own reworks "Ship". The page's
 * blocks share the button list and each lays it out again, so the buttons are put in order every
 * time the toolbar lays them out.
 */
readonly class ShipButtonPositionPlugin
{
    public const string BUTTON = 'polkurier_ship';

    /**
     * Magento's "Ship".
     */
    private const string ANCHOR = 'order_ship';

    /**
     * Leaves the arguments as they are.
     */
    public function beforePushButtons(Toolbar $subject, AbstractBlock $context, ButtonList $buttonList): null
    {
        foreach ($buttonList->getItems() as $items) {
            if (isset($items[self::BUTTON])) {
                $this->sortItems($items);
            }
        }

        return null;
    }

    /**
     * Numbers the buttons of one level in the order they are in, with this one after "Ship", or
     * last.
     *
     * @param array<string, Item> $items sorted
     */
    private function sortItems(array $items): void
    {
        $button = $items[self::BUTTON];
        unset($items[self::BUTTON]);
        $position = 0;
        foreach ($items as $id => $item) {
            $item->setData('sort_order', $position += 10);
            if ($id === self::ANCHOR) {
                $button->setData('sort_order', $position += 10);
            }
        }
        if (!isset($items[self::ANCHOR])) {
            $button->setData('sort_order', $position + 10);
        }
    }
}
