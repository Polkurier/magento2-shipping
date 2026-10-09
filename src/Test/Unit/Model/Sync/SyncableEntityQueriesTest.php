<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sync;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\AddressSearchResultsInterfaceFactory;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\BankAccountSearchResultsInterfaceFactory;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\PackTemplateSearchResultsInterfaceFactory;
use Polkurier\Shipping\Model\Address\AddressRepository;
use Polkurier\Shipping\Model\AddressFactory;
use Polkurier\Shipping\Model\BankAccount\BankAccountRepository;
use Polkurier\Shipping\Model\BankAccountFactory;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateRepository;
use Polkurier\Shipping\Model\PackTemplateFactory;
use Polkurier\Shipping\Model\ResourceModel\Address as AddressResource;
use Polkurier\Shipping\Model\ResourceModel\Address\Collection as AddressCollection;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory as AddressCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\BankAccount as BankAccountResource;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\Collection as BankAccountCollection;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\CollectionFactory as BankAccountCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate as PackTemplateResource;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\Collection as PackTemplateCollection;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory as PackTemplateCollectionFactory;

/**
 * The entries the shipment screens offer: without those deleted here and not synchronized yet,
 * the default first, then by name.
 */
#[CoversClass(AddressRepository::class)]
#[CoversClass(BankAccountRepository::class)]
#[CoversClass(PackTemplateRepository::class)]
#[AllowMockObjectsWithoutExpectations]
class SyncableEntityQueriesTest extends TestCase
{
    /**
     * What the collection was asked: filters and sort orders, in order.
     *
     * @var string[]
     */
    private array $query = [];

    public function testSenderAddressesAndTheDefaultOne(): void
    {
        $plain = $this->entry(AddressInterface::class, false);
        $default = $this->entry(AddressInterface::class, true);
        $repository = new AddressRepository(
            $this->createStub(AddressFactory::class),
            $this->createStub(AddressResource::class),
            $this->factory(AddressCollectionFactory::class, AddressCollection::class, [$default, $plain]),
            $this->createStub(CollectionProcessorInterface::class),
            $this->createStub(AddressSearchResultsInterfaceFactory::class),
        );

        self::assertSame([$default, $plain], $repository->getSenderAddresses(true));
        self::assertSame([
            'deleted_at {"null":true}',
            'type "SENDER"',
            'order is_default DESC',
            'order name ASC',
            'is_cover {"eq":1}',
        ], $this->query, 'Only those that can be printed as the sender.');
        self::assertSame($default, $repository->getDefaultSenderAddress());
    }

    public function testBankAccountsAndTheOneCashOnDeliveryGoesToByDefault(): void
    {
        $first = $this->entry(BankAccountInterface::class, false);
        $repository = new BankAccountRepository(
            $this->createStub(BankAccountFactory::class),
            $this->createStub(BankAccountResource::class),
            $this->factory(BankAccountCollectionFactory::class, BankAccountCollection::class, [$first]),
            $this->createStub(CollectionProcessorInterface::class),
            $this->createStub(BankAccountSearchResultsInterfaceFactory::class),
        );

        self::assertSame($first, $repository->getPreferred(), 'Without a default, the first by name.');
        self::assertSame(['deleted_at {"null":true}', 'order is_default DESC', 'order name ASC'], $this->query);
    }

    public function testPackTemplatesWithoutADefault(): void
    {
        $repository = new PackTemplateRepository(
            $this->createStub(PackTemplateFactory::class),
            $this->createStub(PackTemplateResource::class),
            $this->factory(
                PackTemplateCollectionFactory::class,
                PackTemplateCollection::class,
                [$this->entry(PackTemplateInterface::class, false)],
            ),
            $this->createStub(CollectionProcessorInterface::class),
            $this->createStub(PackTemplateSearchResultsInterfaceFactory::class),
        );

        self::assertCount(1, $repository->getAvailable());
        self::assertNull($repository->getDefault(), 'Unlike the bank account, none unless one is marked.');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $interface
     *
     * @return T
     */
    private function entry(string $interface, bool $default): object
    {
        $entry = $this->createStub($interface);
        $entry->method('isDefault')->willReturn($default);

        return $entry;
    }

    /**
     * @template F of object
     *
     * @param class-string<F> $factoryClass
     * @param class-string<AbstractDb> $collectionClass
     * @param object[] $items
     *
     * @return F
     */
    private function factory(string $factoryClass, string $collectionClass, array $items): object
    {
        $collection = $this->createStub($collectionClass);
        $collection->method('addFieldToFilter')
            ->willReturnCallback(function (string $field, mixed $condition) use ($collection): AbstractDb {
                $this->query[] = $field . ' ' . \json_encode($condition);

                return $collection;
            });
        $collection->method('setOrder')
            ->willReturnCallback(function (string $field, string $direction) use ($collection): AbstractDb {
                $this->query[] = 'order ' . $field . ' ' . $direction;

                return $collection;
            });
        $collection->method('getItems')->willReturn($items);
        $factory = $this->createStub($factoryClass);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }
}
