<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\BankAccount;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\BankAccount;
use Polkurier\Shipping\Model\BankAccount\BankAccountMapper;
use Polkurier\Shipping\Model\BankAccount\BankAccountSyncAdapter;
use Polkurier\Shipping\Model\BankAccount\Publisher;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Builder\Entity\BankAccountBuilderFactory;
use Polkurier\Webservice\Builder\Request\BankAccount\SaveBankAccountMessageBuilderFactory;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(Publisher::class)]
#[CoversClass(BankAccountMapper::class)]
#[AllowMockObjectsWithoutExpectations]
class PublisherTest extends SdkTestCase
{
    private const string IBAN = 'PL61109010140000071219812874';

    private RecordingEventManager $events;

    /** @var array<int, array<string, mixed>> */
    private array $pushed = [];

    /** @var BankAccount[] */
    private array $deleted = [];

    public function testSavingWithACodeSendsTheTokenAndCodeAndRecordsTheAccountAsInSync(): void
    {
        $http = new RecordingClient(self::encodeSuccess([
            'id' => 'b1',
            'name' => 'Main',
            'number' => self::IBAN,
            'default' => true,
        ]));
        $account = $this->createAccount();

        $this->createPublisher($http)->save($account, 'tok', '123456', null);

        $payload = $http->lastPayload();
        self::assertSame('save_bank_account', $payload['apimethod']);
        self::assertSame(self::IBAN, $payload['data']['number'], 'Spaces are taken out of the number.');
        self::assertSame('tok', $payload['data']['two_factor_token']);
        self::assertSame('123456', $payload['data']['two_factor_code']);
        self::assertArrayNotHasKey('access_pin', $payload['data']);
        self::assertSame(
            'b1',
            $this->pushed[0][BankAccountInterface::FIELD_EXT_ID],
            'Recorded as sent, with the id Polkurier gave it.',
        );

        $events = $this->events->listDispatched(ApiEventEnum::BankAccountSaveAfter);
        self::assertCount(1, $events);
        self::assertTrue($events[0]['is_new']);
        self::assertArrayNotHasKey('request', $events[0], 'The request carries the code.');
    }

    public function testWithAPinOnlyThePinIsSent(): void
    {
        $http = new RecordingClient(self::encodeSuccess([
            'id' => 'b1',
            'name' => 'Main',
            'number' => self::IBAN,
            'default' => true,
        ]));

        $this->createPublisher($http)->save($this->createAccount(), 'tok', '1', '9999');

        $data = $http->lastPayload()['data'];
        self::assertSame('9999', $data['access_pin']);
        self::assertArrayNotHasKey('two_factor_token', $data, 'Polkurier takes one confirmation, never both.');
    }

    public function testARefusalLeavesTheAccountUnsentAndReportsWhy(): void
    {
        $http = new RecordingClient((string)\json_encode([
            'status' => 'error',
            'response' => 'Nieprawidłowy kod',
            'error_code' => 'TWO_FACTOR_INVALID_CODE',
        ]));

        try {
            $this->createPublisher($http)->save($this->createAccount(), 'tok', '000000', null);
            self::fail('Expected the refusal to be reported.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Nieprawidłowy kod', $e->getMessage());
        }
        self::assertSame([], $this->pushed);
        self::assertSame([], $this->events->dispatched);
    }

    public function testDeletingSendsThePinAndRemovesTheLocalEntry(): void
    {
        $http = new RecordingClient(self::encodeSuccess(true));
        $account = $this->createAccount()->setExtId('b1');

        $this->createPublisher($http)->delete($account, '9999');

        self::assertSame('delete_bank_account', $http->lastPayload()['apimethod']);
        self::assertSame('9999', $http->lastPayload()['data']['access_pin']);
        self::assertSame([$account], $this->deleted);
        self::assertCount(1, $this->events->listDispatched(ApiEventEnum::BankAccountDeleteAfter));
    }

    protected function setUp(): void
    {
        $this->events = new RecordingEventManager();
    }

    private function createPublisher(RecordingClient $http): Publisher
    {
        $engine = $this->createStub(SyncEngine::class);
        $engine->method('recordPushed')->willReturnCallback(function ($adapter, $local, array $stored): void {
            $this->pushed[] = $stored;
        });
        $repository = $this->createStub(BankAccountRepositoryInterface::class);
        $repository->method('delete')->willReturnCallback(function (BankAccount $account): bool {
            $this->deleted[] = $account;

            return true;
        });
        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(1790000000);

        return new Publisher(
            $this->createClientPool($http),
            new BankAccountMapper(
                NewingObjectManager::createFactory(BankAccountBuilderFactory::class),
                NewingObjectManager::createFactory(SaveBankAccountMessageBuilderFactory::class),
            ),
            $this->createStub(BankAccountSyncAdapter::class),
            $engine,
            $repository,
            new ApiEventDispatcher($this->events, $this->createStub(Logger::class)),
            new ErrorMessageResolver(),
            $dateTime,
        );
    }

    private function createAccount(): BankAccount
    {
        /** @var BankAccount $account */
        $account = (new ObjectManager($this))->getObject(BankAccount::class);

        return $account->setName('Main')->setNumber('PL61 1090 1014 0000 0712 1981 2874')->setDefault(true);
    }
}
