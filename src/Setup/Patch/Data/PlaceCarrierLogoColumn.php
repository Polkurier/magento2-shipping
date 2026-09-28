<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Setup\Patch\Data;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Puts the new Logo column after "Label in checkout" in the carriers lists admins have already
 * arranged. Saved views keep their column positions, and a column they don't know would otherwise
 * end up last.
 */
class PlaceCarrierLogoColumn implements DataPatchInterface
{
    private const string NAMESPACE = 'polkurier_carrier_listing';
    private const string COLUMN = 'logo';
    private const string AFTER = 'custom_label';

    public function __construct(
        private readonly ModuleDataSetupInterface $setup,
        private readonly Json $json,
    ) {}

    public function apply(): static
    {
        $connection = $this->setup->getConnection();
        $table = $this->setup->getTable('ui_bookmark');
        $rows = $connection->fetchPairs(
            $connection->select()->from($table, ['bookmark_id', 'config'])->where('namespace = ?', self::NAMESPACE),
        );
        foreach ($rows as $id => $config) {
            try {
                $data = $this->json->unserialize((string)$config);
            } catch (\InvalidArgumentException) {
                continue;
            }
            if (!\is_array($data)) {
                continue;
            }
            $placed = $this->place($data);
            if ($placed !== $data) {
                $connection->update($table, ['config' => $this->json->serialize($placed)], ['bookmark_id = ?' => $id]);
            }
        }

        return $this;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function place(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }
            $data[$key] = $key === 'positions' && isset($value[self::AFTER]) && !isset($value[self::COLUMN])
                ? $this->insert($value)
                : $this->place($value);
        }

        return $data;
    }

    /**
     * @param array<string, int> $positions column => position
     * @return array<string, int>
     */
    private function insert(array $positions): array
    {
        \asort($positions);
        $ordered = [];
        foreach (\array_keys($positions) as $column) {
            $ordered[] = $column;
            if ($column === self::AFTER) {
                $ordered[] = self::COLUMN;
            }
        }

        return \array_flip($ordered);
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
