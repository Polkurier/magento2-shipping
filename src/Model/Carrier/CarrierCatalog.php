<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Composer\InstalledVersions;
use Magento\Framework\App\CacheInterface;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\AccountCacheKey;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\Sdk\RecentFailureException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\Courier\AvailableCarriersMessage;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\CourierPoint;

/**
 * The carriers the Polkurier account may ship with, and what each supports.
 *
 * The list is account-specific but changes rarely, and checkout needs it on every cart change, so
 * it's cached per account. The response classes only map one way (from the API: empty fields are
 * left out when mapping back, and required ones can't be rebuilt), so the cache holds them
 * PHP-serialized. Only SDK response classes and enums are instantiated on read, the key changes
 * with the SDK version, and an entry that can't be read back whole is dropped and fetched again.
 *
 * When the API can't be asked, every caller would wait for the timeout again, several times per
 * checkout step: the failure is kept for the rest of the request, and for a short while in the
 * cache, and reported again without asking.
 */
class CarrierCatalog
{
    public const string CACHE_TAG = 'POLKURIER_CARRIERS';
    public const int CACHE_LIFETIME = 3600;
    public const int FAILURE_LIFETIME = 90;

    /**
     * The only namespaces a cached carrier list may hold objects of.
     */
    private const array CACHED_NAMESPACES = [
        'Polkurier\\Webservice\\Response\\',
        'Polkurier\\Webservice\\Enum\\',
    ];
    private const string SDK_PACKAGE = 'polkurier/polkurier-sdk';
    private const string FAILURE_SUFFIX = '_failed';

    /**
     * @var array<string, array<string, Carrier>>
     */
    private array $loaded = [];

    /**
     * @var array<string, PolkurierException>
     */
    private array $failed = [];

    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly CacheInterface $cache,
        private readonly ErrorMessages $errorMessages,
        private readonly AvailableCarriers $availableCarriers,
        private readonly AccountCacheKey $cacheKeys,
    ) {}

    /**
     * The carriers the merchant works with: the account's carriers, narrowed to the available
     * carriers set in the configuration, if any. Everything that offers carriers uses this list.
     *
     * @return array<string, Carrier> keyed by service code
     * @throws PolkurierException when the list is not cached and the API cannot be asked
     */
    public function getCarriers(): array
    {
        return $this->availableCarriers->filter($this->getAccountCarriers());
    }

    /**
     * Every carrier of the account, whatever the configuration narrows it to.
     *
     * @return array<string, Carrier> keyed by service code
     * @throws PolkurierException when the list is not cached and the API cannot be asked
     */
    public function getAccountCarriers(): array
    {
        $key = $this->cacheKey();
        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }
        if (isset($this->failed[$key])) {
            throw $this->failed[$key];
        }

        $carriers = $this->readCache($key);
        if ($carriers !== null) {
            return $this->loaded[$key] = $carriers;
        }
        $failure = $this->cache->load($key . self::FAILURE_SUFFIX);
        if (\is_string($failure) && $failure !== '') {
            throw $this->failed[$key] = new RecentFailureException($failure);
        }

        try {
            $carriers = [];
            foreach ($this->clientPool->courier()->availableCarriers(
                new AvailableCarriersMessage(additionalData: true),
            ) as $carrier) {
                $carriers[$carrier->getServiceCode()] = $carrier;
            }
        } catch (PolkurierException $e) {
            $this->cache->save($this->errorMessages->describe($e), $key . self::FAILURE_SUFFIX, [self::CACHE_TAG], self::FAILURE_LIFETIME);

            throw $this->failed[$key] = $e;
        }
        $this->cache->save(\serialize($carriers), $key, [self::CACHE_TAG], self::CACHE_LIFETIME);

        return $this->loaded[$key] = $carriers;
    }

    /**
     * @throws PolkurierException
     */
    public function getCarrier(string $code): ?Carrier
    {
        return $this->getCarriers()[$code] ?? null;
    }

    /**
     * Looks a pickup point up by its id, among the given map providers. Not cached: it confirms a
     * point still exists at the moment it is chosen.
     *
     * @param string[] $providers
     * @throws PolkurierException
     */
    public function findPoint(string $pointId, array $providers): ?CourierPoint
    {
        if ($providers === []) {
            return null;
        }

        return $this->clientPool->courier()->getCourierPoint($pointId, $providers);
    }

    /**
     * Forgets the cached list, e.g. after the credentials changed.
     */
    public function clean(): void
    {
        $this->loaded = [];
        $this->failed = [];
        $this->cache->clean([self::CACHE_TAG]);
    }

    /**
     * @return array<string, Carrier>|null null when nothing usable is cached
     */
    private function readCache(string $key): ?array
    {
        $cached = $this->cache->load($key);
        if (!\is_string($cached) || $cached === '') {
            return null;
        }
        try {
            $classes = $this->cachedClasses($cached);
            $carriers = $classes === null ? false : \unserialize($cached, ['allowed_classes' => $classes]);
            if (\is_array($carriers) && \array_filter(
                $carriers,
                static fn(mixed $carrier): bool => !$carrier instanceof Carrier,
            ) === []) {
                return $carriers;
            }
        } catch (\Throwable) {
            // Unreadable, whatever the reason: fetched again below.
        }
        $this->cache->remove($key);

        return null;
    }

    /**
     * The classes a cached list names, or null when one of them isn't an SDK response class or
     * enum, or no longer exists: an object of it would come back incomplete.
     *
     * @return string[]|null
     */
    private function cachedClasses(string $serialized): ?array
    {
        \preg_match_all('/(?:^|[;{}])[OE]:\d+:"([^":]+)/', $serialized, $matches);
        $classes = \array_values(\array_unique($matches[1]));
        foreach ($classes as $class) {
            $known = false;
            foreach (self::CACHED_NAMESPACES as $namespace) {
                $known = $known || \str_starts_with($class, $namespace);
            }
            if (!$known || !(\class_exists($class) || \enum_exists($class))) {
                return null;
            }
        }

        return $classes;
    }

    private function cacheKey(): string
    {
        return $this->cacheKeys->get('carriers', $this->sdkVersion());
    }

    /**
     * Cached lists of an earlier SDK may no longer fit its classes.
     */
    private function sdkVersion(): string
    {
        try {
            return InstalledVersions::getVersion(self::SDK_PACKAGE) . '@' . InstalledVersions::getReference(self::SDK_PACKAGE);
        } catch (\OutOfBoundsException) {
            return '';
        }
    }
}
