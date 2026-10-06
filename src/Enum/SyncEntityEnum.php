<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

/**
 * The kinds of entries kept both in Magento and on the Polkurier account, and synchronized
 * between them.
 *
 * @api
 */
enum SyncEntityEnum: string
{
    /**
     * Sender addresses.
     */
    case Address = 'address';

    /**
     * Bank accounts for cash on delivery payouts.
     */
    case BankAccount = 'bank_account';

    /**
     * Pack templates.
     */
    case PackTemplate = 'pack_template';

    /**
     * The name the admin knows the kind by, e.g. in synchronization summaries.
     */
    public function label(): string
    {
        return (string)match ($this) {
            self::Address => \__('Addresses'),
            self::BankAccount => \__('Bank accounts'),
            self::PackTemplate => \__('Pack templates'),
        };
    }
}
