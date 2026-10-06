<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Enum\SyncEntityEnum;
use Polkurier\Shipping\Model\Admin\AddressHandler;
use Polkurier\Shipping\Model\Admin\BankAccountHandler;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;
use Polkurier\Shipping\Model\ResourceModel\Address\Collection as AddressCollection;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory as AddressCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\Collection as BankAccountCollection;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\CollectionFactory as BankAccountCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\Collection as PackTemplateCollection;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory as PackTemplateCollectionFactory;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\KeepForLater;
use Polkurier\Shipping\Model\Sync\SyncResult;
use Psr\Log\NullLogger;

#[CoversClass(KeepForLater::class)]
#[AllowMockObjectsWithoutExpectations]
class KeepForLaterTest extends TestCase
{
    /** @var array<string, array<int, array<string, mixed>>> what each handler was asked to save */
    private array $saved = [];

    /** @var string[] */
    private array $messages = [];

    public function testOnlyWhatWasAskedForIsKeptAndTheFirstOfEachIsTheDefault(): void
    {
        $draft = (new Draft())
            ->setNewSender(true)->setKeepingNewSender(true)->setNewSenderName('')->setSenderPointId('PL63197')
            ->setNewSenderAddress(['person' => 'Ewa Lis', 'company' => 'Lis Sp. j.', 'street' => 'Polna', 'house_number' => '3', 'postcode' => '30-001', 'city' => 'Kraków', 'phone' => '501', 'email' => 'e@example.com'])
            ->setNewBankAccount(true)->setKeepingNewBankAccount(false)->setNewBankAccountNumber('6110')
            ->setPacks([
                (new Pack())->setPackType('NST')->setLength(70)->setWidth(25)->setHeight(15)->setWeight(0.7)->setKeepAs('Tuba'),
                (new Pack())->setPackType('ST')->setLength(55)->setWidth(40)->setHeight(30)->setWeight(1.7)->setKeepAs(''),
            ]);

        $this->keeper(allowed: true)->keep($draft);

        self::assertSame('Lis Sp. j., Kraków', $this->saved['address'][0]['name']);
        self::assertSame('SENDER', $this->saved['address'][0]['type']);
        self::assertTrue($this->saved['address'][0]['is_default'], 'The first address.');
        self::assertArrayNotHasKey('flat_number', $this->saved['address'][0], 'What wasn\'t typed in isn\'t saved.');
        self::assertSame('PL63197', $this->saved['address'][0]['point_id'], 'With the drop-off point it was handed over at.');
        self::assertArrayNotHasKey('bank', $this->saved, 'Not asked to keep.');
        self::assertSame([['name' => 'Tuba', 'pack_type' => 'NST', 'length' => 70, 'width' => 25, 'height' => 15, 'weight' => 0.7, 'is_default' => true]], $this->saved['template']);
        self::assertSame(['"Lis Sp. j., Kraków" was saved to addresses.', '"Tuba" was saved to pack templates.'], $this->messages);
    }

    public function testWithoutTheRightToChangeThemNothingIsSaved(): void
    {
        $draft = (new Draft())->setNewSender(true)->setKeepingNewSender(true)->setNewSenderName('Magazyn')->setNewSenderAddress([]);

        $this->keeper(allowed: false)->keep($draft);

        self::assertSame([], $this->saved);
        self::assertSame(['"Magazyn" wasn\'t saved to addresses: you aren\'t allowed to change them.'], $this->messages);
    }

    private function keeper(bool $allowed): KeepForLater
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn($allowed);
        $messages = $this->createStub(ManagerInterface::class);
        foreach (['addSuccessMessage', 'addNoticeMessage', 'addWarningMessage'] as $method) {
            $messages->method($method)->willReturnCallback(function (string $text) use ($messages): ManagerInterface {
                $this->messages[] = $text;

                return $messages;
            });
        }

        return new KeepForLater(
            $this->handler(AddressHandler::class, 'address', 'Addresses', SyncEntityEnum::Address),
            $this->handler(BankAccountHandler::class, 'bank', 'Bank accounts', SyncEntityEnum::BankAccount),
            $this->handler(PackTemplateHandler::class, 'template', 'Pack templates', SyncEntityEnum::PackTemplate),
            $this->factory(AddressCollectionFactory::class, AddressCollection::class),
            $this->factory(BankAccountCollectionFactory::class, BankAccountCollection::class),
            $this->factory(PackTemplateCollectionFactory::class, PackTemplateCollection::class),
            $authorization,
            $messages,
            new NullLogger(),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function handler(string $class, string $key, string $label, SyncEntityEnum $type): object
    {
        $entity = $this->createStub(SyncableInterface::class);
        $entity->method('getId')->willReturn(1);
        $entity->method('getSyncError')->willReturn(null);
        $handler = $this->createStub($class);
        $handler->method('getLabel')->willReturn($label);
        $handler->method('create')->willReturn($entity);
        $handler->method('get')->willReturn($entity);
        $handler->method('save')->willReturnCallback(function ($entity, array $data) use ($key, $type): SyncResult {
            $this->saved[$key][] = $data;

            return new SyncResult($type);
        });

        return $handler;
    }

    /**
     * Collections with nothing in them yet.
     *
     * @template T of object
     *
     * @param class-string<T> $factoryClass
     * @param class-string $collectionClass
     *
     * @return T
     */
    private function factory(string $factoryClass, string $collectionClass): object
    {
        $collection = $this->createStub($collectionClass);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(0);
        $factory = $this->createStub($factoryClass);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }
}
