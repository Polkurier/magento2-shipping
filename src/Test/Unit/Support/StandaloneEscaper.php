<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\ZendEscaper;

/**
 * Magento's escaper, given the helpers it would otherwise take from the object manager, with inline
 * translation off.
 */
final class StandaloneEscaper
{
    public static function create(): Escaper
    {
        $inline = new class implements InlineInterface {
            public function getAdditionalHtmlAttribute($tagName = null)
            {
                return null;
            }

            public function isAllowed()
            {
                return false;
            }

            /**
             * @param array<mixed>|string $body
             */
            public function processResponseBody(&$body, $isJson = false)
            {
                return $this;
            }

            public function getParser(): never
            {
                throw new \LogicException('Inline translation is off.');
            }
        };
        $escaper = new Escaper();
        foreach (['escaper' => new ZendEscaper(), 'translateInline' => $inline] as $property => $value) {
            (new \ReflectionProperty(Escaper::class, $property))->setValue($escaper, $value);
        }

        return $escaper;
    }
}
