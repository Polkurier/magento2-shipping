<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Admin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SynchronizationInterface;
use Polkurier\Shipping\Model\Admin\BankAccountHandler;
use Polkurier\Shipping\Model\Admin\EntityHandlerInterface;
use Polkurier\Shipping\Model\BankAccount;
use Polkurier\Shipping\Model\BankAccount\Confirmation;
use Polkurier\Shipping\Model\BankAccount\Publisher;
use Polkurier\Shipping\Model\BankAccountFactory;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;

#[CoversClass(BankAccountHandler::class)]
#[AllowMockObjectsWithoutExpectations]
class BankAccountHandlerTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>> the account as saved, each time
     */
    private array $saves = [];

    public function testWithoutAConfirmationTheAccountWaitsInMagento(): void
    {
        $publisher = $this->createMock(Publisher::class);
        $publisher->expects(self::never())->method('save');
        $account = $this->account();

        $messages = $this->handler($publisher)->save($account, ['name' => 'Firmowe', 'account_number' => '78150016471216400597700000']);

        self::assertSame('Firmowe', $account->getName());
        self::assertSame(SyncableInterface::STATE_UNCONFIRMED, $account->getSyncState());
        self::assertCount(1, $this->saves);
        self::assertStringContainsString('once you confirm it', $messages[0]);
    }

    public function testAConfirmedAccountIsSentWithTheConfirmationButNeverKeepsIt(): void
    {
        $publisher = $this->createMock(Publisher::class);
        $publisher->expects(self::once())->method('save')->with(
            self::anything(),
            self::callback(static fn(Confirmation $confirmation): bool => $confirmation->isGiven()),
        );
        $account = $this->account();

        $messages = $this->handler($publisher)->save($account, ['name' => 'Firmowe', BankAccountHandler::CONFIRMATION_PIN => '2342']);

        self::assertSame([], $messages);
        foreach (BankAccountHandler::CONFIRMATION_FIELDS as $field) {
            self::assertNull($account->getData($field), $field . ' is used once, never stored.');
            self::assertArrayNotHasKey($field, $this->saves[0]);
        }
    }

    public function testARefusedAccountWaitsWithTheReason(): void
    {
        $publisher = $this->createStub(Publisher::class);
        $publisher->method('save')->willThrowException(new LocalizedException(\__('Polkurier refused: Błędny PIN')));
        $account = $this->account();

        $this->handler($publisher)->save($account, ['name' => 'Firmowe', BankAccountHandler::CONFIRMATION_PIN => '0000']);

        self::assertSame(SyncableInterface::STATE_UNCONFIRMED, $account->getSyncState());
        self::assertSame('Polkurier refused: Błędny PIN', $account->getSyncError());
        self::assertCount(2, $this->saves, 'Saved before sending, and again with the reason.');
    }

    public function testARefusedDeleteSaysHowToDeleteItWithThePin(): void
    {
        $publisher = $this->createStub(Publisher::class);
        $publisher->method('delete')->willThrowException(new LocalizedException(\__('Polkurier refused: PIN required')));

        $this->expectExceptionMessage('Polkurier did not delete bank account Main: Polkurier refused: PIN required If your account locks bank accounts with a PIN');
        $this->handler($publisher)->delete($this->account());
    }

    public function testTheDeleteIsConfirmedWithThePinGiven(): void
    {
        $publisher = $this->createMock(Publisher::class);
        $publisher->expects(self::once())->method('delete')->with(self::anything(), '2342');

        $this->handler($publisher)->delete($this->account(), [BankAccountHandler::CONFIRMATION_PIN => ' 2342 ']);
    }

    public function testWithoutAPinThePlainDeleteIsTried(): void
    {
        $publisher = $this->createMock(Publisher::class);
        $publisher->expects(self::once())->method('delete')->with(self::anything(), null);

        $handler = $this->handler($publisher);
        self::assertInstanceOf(EntityHandlerInterface::class, $handler);
        $handler->delete($this->account());
    }

    private function handler(Publisher $publisher): BankAccountHandler
    {
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);

        $repository = $this->createStub(BankAccountRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (BankAccountInterface $account): BankAccountInterface {
            \assert($account instanceof BankAccount);
            $this->saves[] = $account->getData();

            return $account;
        });

        return new BankAccountHandler(
            $repository,
            $this->createStub(BankAccountFactory::class),
            $publisher,
            $this->createStub(SynchronizationInterface::class),
            new FixedDateTime(),
            new SyncLock($locks),
        );
    }

    private function account(): BankAccount
    {
        /** @var BankAccount $account */
        $account = (new ObjectManager($this))->getObject(BankAccount::class);

        return $account->setWebsiteId(1)->setName('Main');
    }
}
