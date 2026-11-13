<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Carrier\AvailableCarrierFilter;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\PolkurierClient;
use Polkurier\Shipping\Test\Unit\Support\MemoryPolkurierCache;
use Polkurier\Webservice\Api\Service\CourierServiceInterface;
use Polkurier\Webservice\Dto\Response\Carrier;
use Polkurier\Webservice\Dto\Response\CourierPoint;
use Polkurier\Webservice\Dto\Response\ErrorMessage;
use Polkurier\Webservice\Enum\Http\ResponseStatusEnum;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\WebserviceException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(CarrierCatalog::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierCatalogTest extends TestCase
{
    private MemoryPolkurierCache $cache;
    private int $requests = 0;
    private ?\Throwable $failure = null;

    /**
     * @var string[] the points asked of Polkurier
     */
    private array $pointRequests = [];

    private ?\Throwable $pointFailure = null;

    protected function setUp(): void
    {
        $this->cache = new MemoryPolkurierCache();
    }

    public function testAPointIsAskedForOnceAndKeptForADay(): void
    {
        $point = $this->createCatalog()->findPoint('WAW195M', ['INPOST']);
        $again = $this->createCatalog()->findPoint('WAW195M', ['INPOST']);

        self::assertSame('WAW195M', $point?->id);
        self::assertSame('WAW195M', $again?->id);
        self::assertSame(['WAW195M'], $this->pointRequests);
        self::assertContains(CarrierCatalog::POINT_LIFETIME, $this->cache->lifetimes);
        self::assertContains([CacheTagEnum::Points->value], $this->cache->tags, 'Under the points\' own tag.');
    }

    public function testAPointThatDoesntExistIsRememberedTooForADay(): void
    {
        self::assertNull($this->createCatalog()->findPoint('NOPE01', ['INPOST']));
        self::assertNull($this->createCatalog()->findPoint('NOPE01', ['INPOST']));

        self::assertSame(['NOPE01'], $this->pointRequests);
        self::assertContains(CarrierCatalog::POINT_LIFETIME, $this->cache->lifetimes);
    }

    public function testTheProvidersOrderMakesNoOtherEntry(): void
    {
        $this->createCatalog()->findPoint('WAW195M', ['INPOST', 'POCZTA']);
        $this->createCatalog()->findPoint('WAW195M', ['POCZTA', 'INPOST']);

        self::assertSame(['WAW195M'], $this->pointRequests);
    }

    public function testRefreshingTheCarriersKeepsThePoints(): void
    {
        $this->createCatalog()->findPoint('WAW195M', ['INPOST']);
        $this->createCatalog()->refreshAccountCarriers();
        $this->createCatalog()->findPoint('WAW195M', ['INPOST']);

        self::assertSame(['WAW195M'], $this->pointRequests);
    }

    public function testWhileTheCarriersCouldntBeReadNoPointIsAskedFor(): void
    {
        $this->failure = new ConnectionException('Timed out');
        try {
            $this->createCatalog()->getAccountCarriers();
        } catch (ConnectionException) {
        }

        try {
            $this->createCatalog()->findPoint('WAW195M', ['INPOST']);
            self::fail('Not asked: Polkurier failed a moment ago.');
        } catch (RecentFailureException) {
        }
        self::assertSame([], $this->pointRequests);
    }

    public function testAPointLookupThatTimesOutStopsTheNextOnesForAWhile(): void
    {
        $this->pointFailure = new ConnectionException('Timed out');
        try {
            $this->createCatalog()->findPoint('WAW195M', ['INPOST']);
            self::fail('The failure is thrown.');
        } catch (ConnectionException) {
        }
        $this->pointFailure = null;

        try {
            $this->createCatalog()->findPoint('KRA01M', ['INPOST']);
            self::fail('Not asked: Polkurier failed a moment ago.');
        } catch (RecentFailureException) {
        }
        self::assertSame(['WAW195M'], $this->pointRequests);
        self::assertContains(PolkurierCache::FAILURE_LIFETIME, $this->cache->lifetimes);
    }

    public function testAPointRefusedByPolkurierDoesntStopTheOthers(): void
    {
        $this->pointFailure = new WebserviceException(
            new ErrorMessage(ResponseStatusEnum::Error, 'Nieprawidłowy kod punktu', 26, 'VALIDATION_ERROR'),
            $this->createStub(RequestInterface::class),
            $this->createStub(ResponseInterface::class),
        );
        try {
            $this->createCatalog()->findPoint('???', ['INPOST']);
        } catch (WebserviceException) {
        }
        $this->pointFailure = null;

        self::assertSame('KRA01M', $this->createCatalog()->findPoint('KRA01M', ['INPOST'])?->id);
    }

    public function testTheListIsAskedForOnceAndThenReadFromTheCache(): void
    {
        self::assertSame(['DPD'], \array_keys($this->createCatalog()->getAccountCarriers()));
        $carriers = $this->createCatalog()->getAccountCarriers();

        self::assertSame(1, $this->requests);
        self::assertSame('DPD', $carriers['DPD']->serviceCode);
    }

    public function testAnEntryNamingAClassOutsideTheSdkIsDroppedAndFetchedAgain(): void
    {
        $this->createCatalog()->getAccountCarriers();
        $key = \array_key_first($this->cache->entries);
        $this->cache->entries[$key] = \serialize(['DPD' => new \ArrayObject()]);

        $carriers = $this->createCatalog()->getAccountCarriers();

        self::assertSame(2, $this->requests);
        self::assertInstanceOf(Carrier::class, $carriers['DPD']);
    }

    public function testAnEntryNamingAClassThatNoLongerExistsIsDroppedAndFetchedAgain(): void
    {
        $this->createCatalog()->getAccountCarriers();
        $key = \array_key_first($this->cache->entries);
        $this->cache->entries[$key] = 'a:1:{s:3:"DPD";O:45:"Polkurier\Webservice\Dto\Response\GoneCarrier":0:{}}';

        $carriers = $this->createCatalog()->getAccountCarriers();

        self::assertSame(2, $this->requests, 'It would come back as an incomplete object.');
        self::assertInstanceOf(Carrier::class, $carriers['DPD']);
    }

    public function testAnUnreadableEntryIsFetchedAgain(): void
    {
        $this->createCatalog()->getAccountCarriers();
        $key = \array_key_first($this->cache->entries);
        $this->cache->entries[$key] = 'not serialized';

        $this->createCatalog()->getAccountCarriers();

        self::assertSame(2, $this->requests);
    }

    public function testAFailureIsNotAskedAgainInTheSameRequest(): void
    {
        $this->failure = new ConnectionException('Timed out.');
        $catalog = $this->createCatalog();

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
            $this->createCatalog()->getAccountCarriers();
        } catch (ConnectionException) {
        }

        try {
            $this->createCatalog()->getAccountCarriers();
            self::fail('The failure is reported again.');
        } catch (RecentFailureException $e) {
            self::assertSame(
                'Polkurier could not be reached, or did not answer in time: Timed out.',
                (string)(new ErrorMessageResolver())->createPhrase($e),
            );
        }
        self::assertSame(1, $this->requests, 'The next request is told without waiting for the API.');
        self::assertSame([PolkurierCache::FAILURE_LIFETIME], \array_values($this->cache->lifetimes));
    }

    public function testCleaningForgetsTheFailure(): void
    {
        $this->failure = new ConnectionException('Timed out.');
        $catalog = $this->createCatalog();
        try {
            $catalog->getAccountCarriers();
        } catch (ConnectionException) {
        }
        $this->failure = null;

        $catalog->clean();

        self::assertSame(['DPD'], \array_keys($catalog->getAccountCarriers()));
        self::assertSame(2, $this->requests);
    }

    public function testARefreshAsksPastTheCache(): void
    {
        $this->createCatalog()->getAccountCarriers();

        self::assertSame(['DPD'], \array_keys($this->createCatalog()->refreshAccountCarriers()));
        self::assertSame(2, $this->requests);
    }

    public function testAFailedRefreshLeavesTheCachedListAsItWas(): void
    {
        $this->createCatalog()->getAccountCarriers();
        $this->failure = new ConnectionException('Timed out.');
        try {
            $this->createCatalog()->refreshAccountCarriers();
            self::fail('The failure is said.');
        } catch (ConnectionException) {
        }

        self::assertSame(
            ['DPD'],
            \array_keys($this->createCatalog()->getAccountCarriers()),
            'Read from the cache, still there.',
        );
        self::assertSame(2, $this->requests);
    }

    private function createCatalog(): CarrierCatalog
    {
        $courier = $this->createStub(CourierServiceInterface::class);
        $courier->method('availableCarriers')->willReturnCallback(function (): array {
            $this->requests++;
            if ($this->failure !== null) {
                throw $this->failure;
            }

            return [new Carrier('DPD', 'DPD', false)];
        });
        $courier->method('getCourierPoint')->willReturnCallback(function (string $id): ?CourierPoint {
            $this->pointRequests[] = $id;
            if ($this->pointFailure !== null) {
                throw $this->pointFailure;
            }

            return \str_starts_with($id, 'NOPE') ? null : new CourierPoint($id, available: true);
        });
        $clientPool = $this->createStub(PolkurierClient::class);
        $clientPool->method('getCourier')->willReturn($courier);
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getLogin')->willReturn('12345');

        return new CarrierCatalog(
            $clientPool,
            $this->cache,
            new ErrorMessageResolver(),
            new AvailableCarrierFilter($config),
            new AccountCache($this->createStub(PolkurierCache::class), new Json(), $config),
        );
    }
}
