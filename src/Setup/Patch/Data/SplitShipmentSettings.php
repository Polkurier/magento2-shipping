<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Moves the saved shipment settings, in every scope, from the one Shipments group to the smaller
 * groups they're shown in now.
 */
class SplitShipmentSettings implements DataPatchInterface, PatchRevertableInterface
{
    private const string OLD_GROUP = 'carriers/polkurier/shipments/';
    private const string NEW_GROUP = 'carriers/polkurier/%s/';

    /**
     * The group of each setting.
     */
    public const array GROUPS = [
        'available_carriers' => 'shipment_defaults',
        'default_carrier' => 'shipment_defaults',
        'default_shipment_type' => 'shipment_defaults',
        'default_additional_services' => 'shipment_defaults',
        'insure_by_default' => 'shipment_defaults',
        'description_format' => 'shipment_defaults',
        'use_store_information_address' => 'sender',
        'use_store_origin_address' => 'sender',
        'default_sender_email' => 'sender',
        'packing_margin' => 'packing',
        'weight_limit' => 'packing',
        'cod_payment_methods' => 'cod',
        'default_cod_type' => 'cod',
        'default_cod_refund_type' => 'cod',
        'label_format' => 'labels',
        'native_labels' => 'labels',
        'notify_customer' => 'labels',
        'shipment_screen' => 'labels',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
    ) {}

    public function apply(): static
    {
        foreach (self::GROUPS as $field => $group) {
            $this->move(self::OLD_GROUP . $field, \sprintf(self::NEW_GROUP, $group) . $field);
        }

        return $this;
    }

    public function revert(): void
    {
        foreach (self::GROUPS as $field => $group) {
            $this->move(\sprintf(self::NEW_GROUP, $group) . $field, self::OLD_GROUP . $field);
        }
    }

    private function move(string $from, string $to): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->update($this->moduleDataSetup->getTable('core_config_data'), ['path' => $to], ['path = ?' => $from]);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
