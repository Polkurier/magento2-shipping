<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Ui\Component\DataProvider\KeywordSearch;

#[CoversClass(KeywordSearch::class)]
#[AllowMockObjectsWithoutExpectations]
class KeywordSearchTest extends TestCase
{
    public function testEveryWordMustBeFoundInOneOfTheGridsFields(): void
    {
        self::assertSame(
            [
                "main_table.name LIKE '%Magazyn%' OR main_table.number LIKE '%Magazyn%'",
                "main_table.name LIKE '%1090%' OR main_table.number LIKE '%1090%'",
            ],
            $this->search('polkurier_bank_account', ' Magazyn   1090 '),
        );
    }

    public function testTheGridIsKnownByItsTableWithTheShopsPrefix(): void
    {
        self::assertSame(
            ["main_table.name LIKE '%Karton%'"],
            $this->search('shop_polkurier_pack_template', 'Karton', 'shop_'),
        );
    }

    public function testWildcardsTypedInAreSearchedForAsThemselves(): void
    {
        self::assertSame(
            ["main_table.name LIKE '%50\\%\\_off%'"],
            $this->search('polkurier_pack_template', '50%_off'),
        );
    }

    public function testAGridItDoesntKnowOrNoWordsLeaveTheListAsItIs(): void
    {
        self::assertSame([], $this->search('sales_order', 'Kowalski'));
        self::assertSame([], $this->search('polkurier_address', '   '));
    }

    /**
     * @return string[] the conditions added, one per word
     */
    private function search(string $mainTable, string $keyword, string $prefix = ''): array
    {
        $conditions = [];
        $select = $this->createStub(Select::class);
        $select->method('getPart')->willReturn(['main_table' => ['tableName' => $mainTable]]);
        $select->method('where')->willReturnCallback(function (string $condition) use (&$conditions, $select) {
            $conditions[] = $condition;

            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            static fn(string $text, string $value): string => \str_replace('?', "'" . $value . "'", $text),
        );
        $collection = $this->createStub(AbstractCollection::class);
        $collection->method('getTable')->willReturnCallback(static fn(string $table): string => $prefix . $table);
        $collection->method('getSelect')->willReturn($select);
        $collection->method('getConnection')->willReturn($connection);

        (new KeywordSearch())->apply($collection, new Filter(['value' => $keyword]));

        return $conditions;
    }
}
