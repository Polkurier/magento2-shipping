<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Deletes the saved default carrier, in every scope. It picked a carrier for orders placed with
 * other carriers' methods, which the module no longer ships.
 */
class RemoveDefaultCarrier implements DataPatchInterface
{
    private const string PATH = 'carriers/polkurier/shipment_defaults/default_carrier';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
    ) {}

    public function apply(): static
    {
        $this->moduleDataSetup->getConnection()->delete(
            $this->moduleDataSetup->getTable('core_config_data'),
            ['path = ?' => self::PATH],
        );

        return $this;
    }

    public static function getDependencies(): array
    {
        return [SplitShipmentSettings::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
