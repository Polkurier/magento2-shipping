<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Magento's clock stopped at a given moment, in UTC; the real time when none is given.
 */
class FixedDateTime extends DateTime
{
    // Magento's constructor wants the locale's time zone, which this clock doesn't use.
    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
    public function __construct(private readonly ?int $now = null)
    {
    }

    /**
     * @param int|string|\DateTimeInterface|null $input
     */
    public function gmtTimestamp($input = null): int
    {
        return match (true) {
            $input === null => $this->now ?? \time(),
            $input instanceof \DateTimeInterface => $input->getTimestamp(),
            \is_numeric($input) => (int)$input,
            default => (int)\strtotime($input . ' UTC'),
        };
    }

    /**
     * @param string|null $format
     * @param int|string|\DateTimeInterface|null $input
     */
    public function gmtDate($format = null, $input = null): string
    {
        return \gmdate($format ?? 'Y-m-d H:i:s', $this->gmtTimestamp($input));
    }

    /**
     * The store's time is taken to be UTC here.
     *
     * @param string|null $format
     * @param int|string|\DateTimeInterface|null $input
     */
    public function date($format = null, $input = null): string
    {
        return $this->gmtDate($format, $input);
    }
}
