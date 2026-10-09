<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Select;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterApplierInterface;

/**
 * The keyword search of the module's grids: every word must be found in one of the grid's fields.
 * Magento's own searches the main table's full-text index only, which would leave out the order
 * number and the customer the shipment grids join from the order.
 */
readonly class KeywordSearch implements FilterApplierInterface
{
    /**
     * The fields searched, keyed by the grid's main table, named as the grid's collection names them.
     */
    private const array FIELDS = [
        'polkurier_shipment' => [
            'main_table.polkurier_number',
            'main_table.carrier_code',
            'main_table.waybills',
            'main_table.description',
            'main_table.recipient_postcode',
            'main_table.recipient_phone',
            'main_table.status_label',
            'main_table.last_error',
            'sales_order.increment_id',
            'sales_order.customer_email',
            'sales_order.customer_firstname',
            'sales_order.customer_lastname',
        ],
        'polkurier_address' => [
            'main_table.name',
            'main_table.person',
            'main_table.company',
            'main_table.street',
            'main_table.postcode',
            'main_table.city',
            'main_table.phone',
            'main_table.email',
        ],
        'polkurier_bank_account' => [
            'main_table.name',
            'main_table.number',
        ],
        'polkurier_pack_template' => [
            'main_table.name',
        ],
    ];

    public function apply(Collection $collection, Filter $filter): void
    {
        if (!$collection instanceof AbstractDb) {
            throw new \InvalidArgumentException('Database collection required.');
        }
        $from = $collection->getSelect()->getPart(Select::FROM);
        $mainTable = (string)($from['main_table']['tableName'] ?? '');
        $fields = [];
        foreach (self::FIELDS as $table => $tableFields) {
            if ($collection->getConnection()->getTableName($table) === $mainTable) {
                $fields = $tableFields;
            }
        }
        $words = \preg_split('/\s+/u', \trim((string)$filter->getValue()), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if ($fields === [] || $words === []) {
            return;
        }
        $connection = $collection->getConnection();
        foreach ($words as $word) {
            $like = '%' . \addcslashes($word, '%_\\') . '%';
            $collection->getSelect()->where(\implode(' OR ', \array_map(
                static fn(string $field): string => $connection->quoteInto($field . ' LIKE ?', $like),
                $fields,
            )));
        }
    }
}
