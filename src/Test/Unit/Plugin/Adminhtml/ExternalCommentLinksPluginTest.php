<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Sales\Helper\Admin;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Plugin\Adminhtml\ExternalCommentLinksPlugin;

#[CoversClass(ExternalCommentLinksPlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class ExternalCommentLinksPluginTest extends TestCase
{
    public function testTheModulesLinksOpenInANewTabOnceMagentoHasEscapedTheComment(): void
    {
        $comment = 'Waybills: <a href="https://polkurier.pl/t?nr=1&amp;x=2" class="polkurier-external" '
            . 'target="_blank" rel="noopener">WB-1</a>.';
        $escaper = new Escaper();
        // Its inline translator would come from the object manager.
        (new \ReflectionProperty(Escaper::class, 'translateInline'))
            ->setValue($escaper, $this->createStub(InlineInterface::class));
        $escaped = $escaper->escapeHtml($comment, ['b', 'br', 'strong', 'i', 'u', 'a']);

        self::assertStringNotContainsString('target', $escaped, 'Magento drops it.');
        self::assertSame(
            'Waybills: <a href="https://polkurier.pl/t?nr=1&amp;x=2" class="polkurier-external" '
            . 'target="_blank" rel="noopener">WB-1</a>.',
            $this->applyPlugin($escaped),
        );
    }

    public function testOtherLinksAreLeftAsTheyAre(): void
    {
        $comment = 'See <a href="https://example.com/">this</a>.';

        self::assertSame($comment, $this->applyPlugin($comment));
    }

    private function applyPlugin(string $escaped): string
    {
        $result = (new ExternalCommentLinksPlugin())
            ->afterEscapeHtmlWithLinks($this->createStub(Admin::class), $escaped);
        self::assertIsString($result);

        return $result;
    }
}
