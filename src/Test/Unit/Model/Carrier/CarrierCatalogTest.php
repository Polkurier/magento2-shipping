<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Carrier\AvailableCarriers;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\AccountCacheKey;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\Sdk\RecentFailureException;
use Polkurier\Webservice\Api\Service\CourierServiceInterface;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Response\Carrier;

#[CoversClass(CarrierCatalog::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierCatalogTest extends TestCase
{
    /**
     * @var array<string, string> what the cache holds, by key
     */
    private array $cached = [];
    private int $requests = 0;
    private ?\Throwable $failure = null;

    /**
     * @var array<string, int> cache lifetimes, by key
     */
    private array $lifetimes = [];

    public function testTheListIsAskedForOnceAndThenReadFromTheCache(): void
    {
        self::assertSame(['DPD'], \array_keys($this->catalog()->getAccountCarriers()));
        $carriers = $this->catalog()->getAccountCarriers();

        self::assertSame(1, $this->requests);
        self::assertSame('DPD', $carriers['DPD']->getServiceCode());
    }

    public function testAnEntryNamingAClassOutsideTheSdkIsDroppedAndFetchedAgain(): void
    {
        $this->catalog()->getAccountCarriers();
        $key = \array_key_first($this->cached);
        $this->cached[$key] = \serialize(['DPD' => new \ArrayObject()]);

        $carriers = $this->catalog()->getAccountCarriers();

        self::assertSame(2, $this->requests);
        self::assertInstanceOf(Carrier::class, $carriers['DPD']);
    }

    public function testAnEntryNamingAClassThatNoLongerExistsIsDroppedAndFetchedAgain(): void
    {
        $this->catalog()->getAccountCarriers();
        $key = \array_key_first($this->cached);
        $this->cached[$key] = 'a:1:{s:3:"DPD";O:41:"Polkurier\Webservice\Response\GoneCarrier":0:{}}';

        $carriers = $this->catalog()->getAccountCarriers();

        self::assertSame(2, $this->requests, 'It would come back as an incomplete object.');
        self::assertInstanceOf(Carrier::class, $carriers['DPD']);
    }

    public function testAnUnreadableEntryIsFetchedAgain(): void
    {
        $this->catalog()->getAccountCarriers();
        $key = \array_key_first($this->cached);
        $this->cached[$key] = 'not serialized';

        $this->catalog()->getAccountCarriers();

        self::assertSame(2, $this->requests);
    }

    public function testAFailureIsNotAskedAgainInTheSameRequest(): void
    {
        $this->failure = new ConnectionException('Timed out.');
        $catalog = $this->catalog();

        foreach ([1, 2] as $attempt) {
            try {
                $catalog->getAccountCarriers();
                self::fail('The failure is reported every time.');
            } catch (ConnectionException $e) {
                self::assertSame('Timed out.', $e->getMessage());
            }
        }
        self::assertSame(1, $this->requests);
    }

    public function testAFailureIsRememberedForAWhileAcrossRequests(): void
    {
        $this->failure = new ConnectionException('Timed out.');
        try {
            $this->catalog()->getAccountCarriers();
        } catch (ConnectionException) {
        }

        try {
            $this->catalog()->getAccountCarriers();
            self::fail('The failure is reported again.');
        } catch (RecentFailureException $e) {
            self::assertSame('Polkurier could not be reached, or did not answer in time: Timed out.', (new ErrorMessages())->describe($e));
        }
        self::assertSame(1, $this->requests, 'The next request is told without waiting for the API.');
        self::assertSame([CarrierCatalog::FAILURE_LIFETIME], \array_values($this->lifetimes));
    }

    public function testCleaningForgetsTheFailure(): void
    {
        $this->failure = new ConnectionException('Timed out.');
        $catalog = $this->catalog();
        try {
            $catalog->getAccountCarriers();
        } catch (ConnectionException) {
        }
        $this->failure = null;

        $catalog->clean();

        self::assertSame(['DPD'], \array_keys($catalog->getAccountCarriers()));
        self::assertSame(2, $this->requests);
    }

    private function catalog(): CarrierCatalog
    {
        $courier = $this->createStub(CourierServiceInterface::class);
        $courier->method('availableCarriers')->willReturnCallback(function (): array {
            $this->requests++;
            if ($this->failure !== null) {
                throw $this->failure;
            }

            return [new Carrier('DPD', 'DPD', false)];
        });
        $clientPool = $this->createStub(ClientPool::class);
        $clientPool->method('courier')->willReturn($courier);
        $config = $this->createStub(Config::class);
        $config->method('getLogin')->willReturn('12345');

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn(string $key): string|false => $this->cached[$key] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $key, array $tags, int $lifetime): bool {
            $this->cached[$key] = $data;
            $this->lifetimes[$key] = $lifetime;

            return true;
        });
        $cache->method('clean')->willReturnCallback(function (): bool {
            $this->cached = [];

            return true;
        });
        $cache->method('remove')->willReturnCallback(function (string $key): bool {
            unset($this->cached[$key]);

            return true;
        });

        return new CarrierCatalog($clientPool, $cache, new ErrorMessages(), new AvailableCarriers($config), new AccountCacheKey($config));
    }
}
