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
use Polkurier\Webservice\Api\Service\CourierServiceInterface;
use Polkurier\Webservice\Enum\Http\ResponseStatusEnum;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\WebserviceException;
use Polkurier\Webservice\Http\Response\ErrorMessage;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\CourierPoint;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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

    /**
     * @var array<string, string[]> cache tags, by key
     */
    private array $tags = [];

    /**
     * @var string[] the points asked of Polkurier
     */
    private array $pointRequests = [];

    private ?\Throwable $pointFailure = null;

    public function testAPointIsAskedForOnceAndKeptForADay(): void
    {
        $point = $this->createCatalog()->findPoint('WAW195M', ['INPOST']);
        $again = $this->createCatalog()->findPoint('WAW195M', ['INPOST']);

        self::assertSame('WAW195M', $point?->getId());
        self::assertSame('WAW195M', $again?->getId());
        self::assertSame(['WAW195M'], $this->pointRequests);
        self::assertContains(CarrierCatalog::POINT_LIFETIME, $this->lifetimes);
        self::assertContains([CacheTagEnum::Points->value], $this->tags, 'Under the points\' own tag.');
    }

    public function testAPointThatDoesntExistIsRememberedTooForADay(): void
    {
        self::assertNull($this->createCatalog()->findPoint('NOPE01', ['INPOST']));
        self::assertNull($this->createCatalog()->findPoint('NOPE01', ['INPOST']));

        self::assertSame(['NOPE01'], $this->pointRequests);
        self::assertContains(CarrierCatalog::POINT_LIFETIME, $this->lifetimes);
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
        self::assertContains(PolkurierCache::FAILURE_LIFETIME, $this->lifetimes);
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

        self::assertSame('KRA01M', $this->createCatalog()->findPoint('KRA01M', ['INPOST'])?->getId());
    }

    public function testTheListIsAskedForOnceAndThenReadFromTheCache(): void
    {
        self::assertSame(['DPD'], \array_keys($this->createCatalog()->getAccountCarriers()));
        $carriers = $this->createCatalog()->getAccountCarriers();

        self::assertSame(1, $this->requests);
        self::assertSame('DPD', $carriers['DPD']->getServiceCode());
    }

    public function testAnEntryNamingAClassOutsideTheSdkIsDroppedAndFetchedAgain(): void
    {
        $this->createCatalog()->getAccountCarriers();
        $key = \array_key_first($this->cached);
        $this->cached[$key] = \serialize(['DPD' => new \ArrayObject()]);

        $carriers = $this->createCatalog()->getAccountCarriers();

        self::assertSame(2, $this->requests);
        self::assertInstanceOf(Carrier::class, $carriers['DPD']);
    }

    public function testAnEntryNamingAClassThatNoLongerExistsIsDroppedAndFetchedAgain(): void
    {
        $this->createCatalog()->getAccountCarriers();
        $key = \array_key_first($this->cached);
        $this->cached[$key] = 'a:1:{s:3:"DPD";O:41:"Polkurier\Webservice\Response\GoneCarrier":0:{}}';

        $carriers = $this->createCatalog()->getAccountCarriers();

        self::assertSame(2, $this->requests, 'It would come back as an incomplete object.');
        self::assertInstanceOf(Carrier::class, $carriers['DPD']);
    }

    public function testAnUnreadableEntryIsFetchedAgain(): void
    {
        $this->createCatalog()->getAccountCarriers();
        $key = \array_key_first($this->cached);
        $this->cached[$key] = 'not serialized';

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
        self::assertSame([PolkurierCache::FAILURE_LIFETIME], \array_values($this->lifetimes));
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

        $cache = $this->createStub(PolkurierCache::class);
        $cache->method('load')->willReturnCallback(fn(string $key): string|false => $this->cached[$key] ?? false);
        $cache->method('save')
            ->willReturnCallback(function (string $data, string $key, array $tags, int $lifetime): bool {
                $this->cached[$key] = $data;
                $this->lifetimes[$key] = $lifetime;
                $this->tags[$key] = $tags;

                return true;
            });
        $cache->method('cleanTags')->willReturnCallback(function (CacheTagEnum ...$kinds): bool {
            $tags = \array_map(static fn(CacheTagEnum $tag): string => $tag->value, $kinds);
            foreach (\array_keys($this->cached) as $key) {
                if ($tags === [] || \array_intersect($tags, $this->tags[$key] ?? []) !== []) {
                    unset($this->cached[$key]);
                }
            }

            return true;
        });
        $cache->method('remove')->willReturnCallback(function (string $key): bool {
            unset($this->cached[$key]);

            return true;
        });

        return new CarrierCatalog(
            $clientPool,
            $cache,
            new ErrorMessageResolver(),
            new AvailableCarrierFilter($config),
            new AccountCache($this->createStub(PolkurierCache::class), new Json(), $config),
        );
    }
}
