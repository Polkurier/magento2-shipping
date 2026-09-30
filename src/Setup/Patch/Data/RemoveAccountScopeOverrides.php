<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Deletes the Polkurier account settings saved for a website or store view. The account is one
 * for the whole installation and is read from the default scope only, so such values would sit
 * in the database unused, and come back if the setting were ever scoped again.
 */
class RemoveAccountScopeOverrides implements DataPatchInterface
{
    /**
     * The account's settings: a group, or a single field.
     */
    private const array PATHS = [
        'carriers/polkurier/general/%',
        'carriers/polkurier/sync/%',
        'carriers/polkurier/shipment_defaults/available_carriers',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
    ) {}

    public function apply(): static
    {
        $connection = $this->moduleDataSetup->getConnection();
        $paths = \array_map(static fn(string $path): string => $connection->quoteInto('path LIKE ?', $path), self::PATHS);
        $connection->delete(
            $this->moduleDataSetup->getTable('core_config_data'),
            [
                'scope <> ?' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                '(' . \implode(' OR ', $paths) . ')',
            ],
        );

        return $this;
    }

    public static function getDependencies(): array
    {
        return [RemoveDefaultCarrier::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
