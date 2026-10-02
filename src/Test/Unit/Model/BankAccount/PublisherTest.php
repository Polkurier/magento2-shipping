<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\BankAccount;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\ApiEventEnum;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Model\BankAccount;
use Polkurier\Shipping\Model\BankAccount\Confirmation;
use Polkurier\Shipping\Model\BankAccount\Publisher;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Mapper\BankAccountMapper;
use Polkurier\Shipping\Model\Sync\Adapter\BankAccountAdapter;
use Polkurier\Shipping\Model\Sync\SyncEngine;
use Polkurier\Shipping\Test\Unit\Support\RecordingEventManager;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Testing\RecordingClient;
use Psr\Log\NullLogger;

#[CoversClass(Publisher::class)]
#[CoversClass(Confirmation::class)]
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

    protected function setUp(): void
    {
        $this->events = new RecordingEventManager();
    }

    public function testSavingWithACodeSendsTheTokenAndCodeAndRecordsTheAccountAsInSync(): void
    {
        $http = new RecordingClient(self::success(['id' => 'b1', 'name' => 'Main', 'number' => self::IBAN, 'default' => true]));
        $account = $this->account();

        $this->publisher($http)->save($account, new Confirmation(twoFactorToken: 'tok', twoFactorCode: '123456'));

        $payload = $http->lastPayload();
        self::assertSame('save_bank_account', $payload['apimethod']);
        self::assertSame(self::IBAN, $payload['data']['number'], 'Spaces are taken out of the number.');
        self::assertSame('tok', $payload['data']['two_factor_token']);
        self::assertSame('123456', $payload['data']['two_factor_code']);
        self::assertArrayNotHasKey('access_pin', $payload['data']);
        self::assertSame('b1', $this->pushed[0]['id'], 'Recorded as sent, with the id Polkurier gave it.');

        $events = $this->events->of(ApiEventEnum::BankAccountSaveAfter);
        self::assertCount(1, $events);
        self::assertTrue($events[0]['is_new']);
        self::assertArrayNotHasKey('request', $events[0], 'The request carries the code.');
    }

    public function testWithAPinOnlyThePinIsSent(): void
    {
        $http = new RecordingClient(self::success(['id' => 'b1', 'name' => 'Main', 'number' => self::IBAN, 'default' => true]));

        $this->publisher($http)->save($this->account(), new Confirmation(pin: '9999', twoFactorToken: 'tok', twoFactorCode: '1'));

        $data = $http->lastPayload()['data'];
        self::assertSame('9999', $data['access_pin']);
        self::assertArrayNotHasKey('two_factor_token', $data, 'Polkurier takes one confirmation, never both.');
    }

    public function testARefusalLeavesTheAccountUnsentAndReportsWhy(): void
    {
        $http = new RecordingClient((string)\json_encode(['status' => 'error', 'response' => 'Nieprawidłowy kod', 'error_code' => 'TWO_FACTOR_INVALID_CODE']));

        try {
            $this->publisher($http)->save($this->account(), new Confirmation(twoFactorToken: 'tok', twoFactorCode: '000000'));
            self::fail('Expected the refusal to be reported.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('Nieprawidłowy kod', $e->getMessage());
        }
        self::assertSame([], $this->pushed);
        self::assertSame([], $this->events->dispatched);
    }

    public function testDeletingSendsThePinAndRemovesTheLocalEntry(): void
    {
        $http = new RecordingClient(self::success(true));
        $account = $this->account()->setExtId('b1');

        $this->publisher($http)->delete($account, '9999');

        self::assertSame('delete_bank_account', $http->lastPayload()['apimethod']);
        self::assertSame('9999', $http->lastPayload()['data']['access_pin']);
        self::assertSame([$account], $this->deleted);
        self::assertCount(1, $this->events->of(ApiEventEnum::BankAccountDeleteAfter));
    }

    private function publisher(RecordingClient $http): Publisher
    {
        $engine = $this->createStub(SyncEngine::class);
        $engine->method('pushed')->willReturnCallback(function ($adapter, $local, array $stored): void {
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
            $this->clientPool($http),
            new BankAccountMapper(),
            $this->createStub(BankAccountAdapter::class),
            $engine,
            $repository,
            new ApiEventDispatcher($this->events, new NullLogger()),
            new ErrorMessages(),
            $dateTime,
        );
    }

    private function account(): BankAccount
    {
        /** @var BankAccount $account */
        $account = (new ObjectManager($this))->getObject(BankAccount::class);

        return $account->setName('Main')->setNumber('PL61 1090 1014 0000 0712 1981 2874')->setDefault(true);
    }
}
