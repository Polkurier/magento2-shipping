<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Every shipping carrier of the store, enabled or not, since orders may have been placed with a
 * carrier switched off since. Each is listed by its title, with its code when the title says
 * something else.
 */
readonly class ShippingCarrier extends AbstractOptions
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
    ) {}

    public function toArray(): array
    {
        $options = [];
        foreach ((array)$this->scopeConfig->getValue('carriers') as $code => $carrier) {
            if (!\is_array($carrier) || empty($carrier['model'])) {
                continue;
            }
            $title = \trim((string)($carrier['title'] ?? ''));
            $options[(string)$code] = match (true) {
                $title === '' => (string)$code,
                \strcasecmp($title, (string)$code) === 0 => $title,
                default => \sprintf('%s (%s)', $title, $code),
            };
        }
        \asort($options, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $options;
    }
}
