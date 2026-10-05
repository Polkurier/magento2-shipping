<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\ZendEscaper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Ui\Component\Listing\Column\WaybillsColumn;

#[CoversClass(WaybillsColumn::class)]
#[AllowMockObjectsWithoutExpectations]
class WaybillsColumnTest extends TestCase
{
    public function testWaybillsLinkToTheTrackingPage(): void
    {
        self::assertSame(
            '<a href="https://track.example/?n=WB-1&amp;x=1" target="_blank" rel="noopener">WB-1</a>, '
            . '<a href="https://track.example/?n=WB-1&amp;x=1" target="_blank" rel="noopener">WB-2</a>',
            $this->cell('WB-1,WB-2', 'https://track.example/?n=WB-1&x=1'),
        );
    }

    public function testNothingFromPolkurierReachesThePageUnescaped(): void
    {
        $cell = $this->cell('<b>WB-1</b>', 'javascript:alert(1)');

        self::assertStringNotContainsString('javascript:', $cell);
        self::assertStringContainsString('&lt;b&gt;WB-1&lt;/b&gt;', $cell);
    }

    private function cell(string $waybills, string $url): string
    {
        $context = $this->createStub(ContextInterface::class);
        $context->method('getProcessor')->willReturn($this->createStub(Processor::class));
        $column = new WaybillsColumn($context, $this->createStub(UiComponentFactory::class), $this->escaper(), [], ['name' => 'waybills']);

        $source = $column->prepareDataSource(['data' => ['items' => [['waybills' => $waybills, 'tracking_url' => $url]]]]);

        return (string)$source['data']['items'][0]['waybills'];
    }

    /**
     * Magento's escaper, given the helpers it would otherwise take from the object manager.
     */
    private function escaper(): Escaper
    {
        $escaper = new Escaper();
        foreach (['escaper' => new ZendEscaper(), 'translateInline' => $this->createStub(InlineInterface::class)] as $property => $value) {
            (new \ReflectionProperty(Escaper::class, $property))->setValue($escaper, $value);
        }

        return $escaper;
    }
}
