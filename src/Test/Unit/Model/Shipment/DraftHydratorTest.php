<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\Shipment\DraftFactory;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;

#[CoversClass(DraftHydrator::class)]
#[CoversClass(Draft::class)]
#[AllowMockObjectsWithoutExpectations]
class DraftHydratorTest extends TestCase
{
    public function testTheScreenIsToldWhenTheParcelsAreAnEstimate(): void
    {
        $hydrator = new DraftHydrator(
            $this->createStub(DraftFactory::class),
            $this->createStub(PackFactory::class),
            $this->createStub(ItemFactory::class),
        );

        self::assertFalse($hydrator->toArray(new Draft())['packs_estimated']);
        self::assertTrue($hydrator->toArray((new Draft())->setPacksEstimated(true))['packs_estimated']);
    }

    public function testASenderAndAnAccountTypedInGoAndComeAsNew(): void
    {
        $draftFactory = $this->createStub(DraftFactory::class);
        $draftFactory->method('create')->willReturnCallback(static fn(): Draft => new Draft());
        $hydrator = new DraftHydrator($draftFactory, $this->createStub(PackFactory::class), $this->createStub(ItemFactory::class));

        $draft = $hydrator->fromArray([
            'sender_address_id' => 'new', 'new_sender_city' => ' Kraków ', 'keep_new_sender' => '1', 'new_sender_name' => 'Magazyn',
            'cod_bank_account_id' => 'new', 'new_bank_account_number' => '6110', 'keep_new_bank_account' => '0',
        ]);

        self::assertTrue($draft->isNewSender());
        self::assertNull($draft->getSenderAddressId());
        self::assertSame('Kraków', $draft->getNewSenderAddress()['city']);
        self::assertTrue($draft->isKeepingNewSender());
        self::assertSame('Magazyn', $draft->getNewSenderName());
        self::assertTrue($draft->isNewBankAccount());
        self::assertFalse($draft->isKeepingNewBankAccount());
        $data = $hydrator->toArray($draft);
        self::assertSame('new', $data['sender_address_id']);
        self::assertSame('new', $data['cod_bank_account_id']);
        self::assertSame('6110', $data['new_bank_account_number']);
        self::assertFalse($hydrator->fromArray(['sender_address_id' => '7'])->isNewSender());
    }
}
