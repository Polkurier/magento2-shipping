<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Polkurier\Shipping\Model\Shipment\ItemDimensions;

/**
 * Product attributes for the size of one unit of a product as it's shipped, for packing orders into
 * parcels. Admin only: they're never shown in the store.
 */
class AddProductDimensions implements DataPatchInterface, PatchRevertableInterface
{
    public const string GROUP = 'Shipping dimensions';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
    ) {}

    public function apply(): static
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $labels = [
            ItemDimensions::LENGTH => 'Length (cm)',
            ItemDimensions::WIDTH => 'Width (cm)',
            ItemDimensions::HEIGHT => 'Height (cm)',
        ];
        $sortOrder = 10;
        foreach ($labels as $code => $label) {
            $eavSetup->addAttribute(Product::ENTITY, $code, [
                'type' => 'decimal',
                'label' => $label,
                'input' => 'text',
                'frontend_class' => 'validate-zero-or-greater',
                'note' => 'Of one unit as it is shipped, packaging included. Used to pack orders into parcels.',
                'required' => false,
                'user_defined' => true,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'unique' => false,
                'apply_to' => 'simple,configurable,bundle',
                'group' => self::GROUP,
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        return $this;
    }

    public function revert(): void
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        foreach ([ItemDimensions::LENGTH, ItemDimensions::WIDTH, ItemDimensions::HEIGHT] as $code) {
            $eavSetup->removeAttribute(Product::ENTITY, $code);
        }
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
