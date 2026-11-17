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
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Address as AddressResource;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as BankAccountResource;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate as PackTemplateResource;
use Polkurier\Shipping\Model\ResourceModel\Shipment as ShipmentResource;

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
        ShipmentResource::TABLE_NAME => [
            'main_table.' . ShipmentInterface::FIELD_POLKURIER_NUMBER,
            'main_table.' . ShipmentInterface::FIELD_CARRIER_CODE,
            'main_table.' . ShipmentInterface::FIELD_WAYBILLS,
            'main_table.' . ShipmentInterface::FIELD_DESCRIPTION,
            'main_table.' . ShipmentInterface::FIELD_RECIPIENT_POSTCODE,
            'main_table.' . ShipmentInterface::FIELD_RECIPIENT_PHONE,
            'main_table.' . ShipmentInterface::FIELD_STATUS_LABEL,
            'main_table.' . ShipmentInterface::FIELD_LAST_ERROR,
            'sales_order.increment_id',
            'sales_order.customer_email',
            'sales_order.customer_firstname',
            'sales_order.customer_lastname',
        ],
        AddressResource::TABLE_NAME => [
            'main_table.' . AddressInterface::FIELD_NAME,
            'main_table.' . AddressInterface::FIELD_PERSON,
            'main_table.' . AddressInterface::FIELD_COMPANY,
            'main_table.' . AddressInterface::FIELD_STREET,
            'main_table.' . AddressInterface::FIELD_POSTCODE,
            'main_table.' . AddressInterface::FIELD_CITY,
            'main_table.' . AddressInterface::FIELD_PHONE,
            'main_table.' . AddressInterface::FIELD_EMAIL,
        ],
        BankAccountResource::TABLE_NAME => [
            'main_table.' . BankAccountInterface::FIELD_NAME,
            'main_table.' . BankAccountInterface::FIELD_NUMBER,
        ],
        PackTemplateResource::TABLE_NAME => [
            'main_table.' . PackTemplateInterface::FIELD_NAME,
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
