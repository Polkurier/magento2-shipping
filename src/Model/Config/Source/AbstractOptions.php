<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * A fixed list of options, value => label.
 */
abstract class AbstractOptions implements OptionSourceInterface
{
    /**
     * @return array<string, string>
     */
    abstract public function toArray(): array;

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->toArray() as $value => $label) {
            $options[] = ['value' => (string)$value, 'label' => $label];
        }

        return $options;
    }
}
