<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\MoneyBox;

/**
 * Polkurier order numbers typed one per line, or separated by commas or spaces.
 */
final class OrderNumbers
{
    /**
     * @return string[]
     */
    public static function parse(string $text): array
    {
        return \array_values(\array_unique(\array_filter(\preg_split('/[\s,;]+/', \trim($text)) ?: [])));
    }
}
