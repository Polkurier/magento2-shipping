<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

/**
 * Every carrier of the Polkurier account configured for the scope being edited, for choosing the
 * available carriers.
 */
class AccountCarrier extends Carrier
{
    public function toArray(): array
    {
        $options = parent::toArray();
        unset($options['']);

        return $options;
    }

    protected function loadCarriers(int $storeId): array
    {
        return $this->catalog->getAccountCarriers($storeId);
    }
}
