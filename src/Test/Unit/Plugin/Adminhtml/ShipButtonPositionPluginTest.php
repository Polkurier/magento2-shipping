<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Item;
use Magento\Backend\Block\Widget\Button\Toolbar;
use Magento\Framework\View\Element\AbstractBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Plugin\Adminhtml\ShipButtonPositionPlugin;

#[CoversClass(ShipButtonPositionPlugin::class)]
class ShipButtonPositionPluginTest extends TestCase
{
    public function testTheButtonFollowsShip(): void
    {
        self::assertSame(
            ['order_edit', 'order_invoice', 'order_ship', 'polkurier_ship', 'order_reorder'],
            $this->laidOut(['order_edit', 'order_invoice', 'order_ship', 'order_reorder', 'polkurier_ship']),
        );
    }

    public function testWithoutShipItTakesItsPlace(): void
    {
        self::assertSame(
            ['order_cancel', 'order_hold', 'order_invoice', 'polkurier_ship', 'order_reorder'],
            $this->laidOut(['order_cancel', 'order_hold', 'order_invoice', 'order_reorder', 'polkurier_ship']),
        );
    }

    public function testOtherButtonsKeepTheirOrder(): void
    {
        self::assertSame(['order_reorder', 'something_else', 'polkurier_ship'], $this->laidOut(['order_reorder', 'something_else', 'polkurier_ship']));
        self::assertSame(['order_ship', 'order_reorder'], $this->laidOut(['order_ship', 'order_reorder']), 'Not on the page: nothing changes.');
    }

    /**
     * Buttons as Magento adds them to the order page, all with one sort order: in the order given.
     *
     * @param string[] $ids
     * @return string[] in the order the toolbar lays them out
     */
    private function laidOut(array $ids): array
    {
        $items = [];
        foreach ($ids as $id) {
            $items[$id] = new Item(['id' => $id, 'sort_order' => 70]);
        }
        $list = $this->createStub(ButtonList::class);
        $list->method('getItems')->willReturn([0 => $items]);

        (new ShipButtonPositionPlugin())->beforePushButtons($this->createStub(Toolbar::class), $this->createStub(AbstractBlock::class), $list);
        // Stable, as PHP's sorting is: equal sort orders keep the order the buttons were added in.
        \uasort($items, static fn(Item $a, Item $b): int => (int)$a->getSortOrder() <=> (int)$b->getSortOrder());

        return \array_keys($items);
    }
}
