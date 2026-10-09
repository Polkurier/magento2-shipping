<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Composer\InstalledVersions;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\Courier\AvailableCarriersMessage;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\CourierPoint;

/**
 * The carriers the Polkurier account may ship with, and what each supports.
 *
 * The list is account-specific but changes rarely, and checkout needs it on every cart change, so
 * it's cached per account for a day; {@see \Polkurier\Shipping\Cron\RefreshCarriersTask} refreshes
 * it daily. The response classes only map one way (from the API: empty fields are left out when
 * mapping back, and required ones can't be rebuilt), so the cache holds them PHP-serialized. Only
 * SDK response classes and enums are instantiated on read, the key changes with the SDK version,
 * and an entry that can't be read back whole is dropped and fetched again.
 *
 * When the API can't be asked, every caller would wait for the timeout again, several times per
 * checkout step. So the failure is kept for the rest of the request, and briefly in the cache, and
 * reported again without asking.
 *
 * Pickup points are looked up one by one, as they're chosen, and cached for a day: a point hardly
 * ever moves or closes. Their entries have a tag of their own, so refreshing the carriers keeps
 * them. A point isn't looked up while the carriers or the points couldn't be read a moment ago.
 */
class CarrierCatalog implements ResetAfterRequestInterface
{
    public const int CACHE_LIFETIME = 86400;
    public const int FAILURE_LIFETIME = 90;

    /**
     * How long a looked-up point is cached, whether it was found or not.
     */
    public const int POINT_LIFETIME = 86400;

    /**
     * The only namespaces a cached carrier list may hold objects of.
     */
    private const array CACHED_NAMESPACES = [
        'Polkurier\\Webservice\\Response\\',
        'Polkurier\\Webservice\\Enum\\',
    ];

    private const string SDK_PACKAGE = 'polkurier/polkurier-sdk';
    private const string FAILURE_SUFFIX = '_failed';
    private const string NO_POINT = 'none';

    /**
     * @var array<string, array<string, Carrier>>
     */
    private array $loaded = [];

    /**
     * @var array<string, PolkurierException>
     */
    private array $failed = [];

    public function __construct(
        private readonly PolkurierClientInterface $polkurierClient,
        private readonly PolkurierCache $polkurierCache,
        private readonly ErrorMessageResolver $errorMessageResolver,
        private readonly AvailableCarrierFilter $availableCarrierFilter,
        private readonly AccountCache $accountCache,
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
        return $this->availableCarrierFilter->filter($this->getAccountCarriers());
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
        $failure = $this->polkurierCache->load($key . self::FAILURE_SUFFIX);
        if (\is_string($failure) && $failure !== '') {
            throw $this->failed[$key] = new RecentFailureException($failure);
        }

        try {
            $carriers = $this->fetch();
        } catch (PolkurierException $e) {
            $this->polkurierCache->save(
                (string)$this->errorMessageResolver->phrase($e),
                $key . self::FAILURE_SUFFIX,
                [CacheTagEnum::Carriers->value, CacheTagEnum::Failures->value],
                self::FAILURE_LIFETIME,
            );

            throw $this->failed[$key] = $e;
        }

        return $this->keep($key, $carriers);
    }

    /**
     * Reads the account's carriers from Polkurier again, bypassing the cache. What was cached for
     * the account is dropped only once the new list is in, so a failure leaves it as it was.
     *
     * @return array<string, Carrier> by carrier code
     * @throws PolkurierException
     */
    public function refreshAccountCarriers(): array
    {
        $carriers = $this->fetch();
        $this->clean();

        return $this->keep($this->cacheKey(), $carriers);
    }

    /**
     * @throws PolkurierException
     */
    public function getCarrier(string $code): ?Carrier
    {
        return $this->getCarriers()[$code] ?? null;
    }

    /**
     * Looks a pickup point up by its id, among the given map providers.
     *
     * @param string[] $providers
     *
     * @return CourierPoint|null null when there is no such point
     * @throws PolkurierException {@see RecentFailureException} while Polkurier couldn't be asked a moment ago
     */
    public function findPoint(string $pointId, array $providers): ?CourierPoint
    {
        if ($providers === []) {
            return null;
        }
        \sort($providers);
        $key = $this->accountCache->key('point', $this->sdkVersion(), $pointId, \implode(',', $providers));
        $cached = $this->polkurierCache->load($key);
        if ($cached === self::NO_POINT) {
            return null;
        }
        $point = \is_string($cached) && $cached !== '' ? $this->unserializeCached($cached) : null;
        if ($point instanceof CourierPoint) {
            return $point;
        }
        foreach ([$this->cacheKey(), $this->pointsFailureKey()] as $failing) {
            $failure = $this->failed[$failing] ?? $this->polkurierCache->load($failing . self::FAILURE_SUFFIX);
            if ($failure instanceof PolkurierException) {
                throw $failure;
            }
            if (\is_string($failure) && $failure !== '') {
                throw new RecentFailureException($failure);
            }
        }

        try {
            $point = $this->polkurierClient->courier()->getCourierPoint($pointId, $providers);
        } catch (PolkurierException $e) {
            // Polkurier unreachable or busy: not asked again for a while. A refusal of this point
            // says nothing of the next one.
            if ($this->errorMessageResolver->isTransient($e)) {
                $this->polkurierCache->save(
                    (string)$this->errorMessageResolver->phrase($e),
                    $this->pointsFailureKey() . self::FAILURE_SUFFIX,
                    [CacheTagEnum::Points->value, CacheTagEnum::Failures->value],
                    self::FAILURE_LIFETIME,
                );
                $this->failed[$this->pointsFailureKey()] = $e;
            }

            throw $e;
        }
        $data = $point === null ? self::NO_POINT : \serialize($point);
        $this->polkurierCache->save($data, $key, [CacheTagEnum::Points->value], self::POINT_LIFETIME);

        return $point;
    }

    /**
     * Forgets the cached list, e.g. after the credentials changed.
     */
    public function clean(): void
    {
        $this->loaded = [];
        $this->failed = [];
        // Polkurier answered, or is to be asked afresh: whatever failed a moment ago is asked again.
        $this->polkurierCache->cleanTags(CacheTagEnum::Carriers, CacheTagEnum::Notices, CacheTagEnum::Failures);
    }

    public function _resetState(): void
    {
        $this->loaded = [];
        $this->failed = [];
    }

    /**
     * @return array<string, Carrier> by carrier code
     * @throws PolkurierException
     */
    private function fetch(): array
    {
        $carriers = [];
        $message = new AvailableCarriersMessage(additionalData: true);
        $available = $this->polkurierClient->courier()->availableCarriers($message);
        foreach ($available as $carrier) {
            $carriers[$carrier->getServiceCode()] = $carrier;
        }

        return $carriers;
    }

    /**
     * @param array<string, Carrier> $carriers
     *
     * @return array<string, Carrier>
     */
    private function keep(string $key, array $carriers): array
    {
        $this->polkurierCache->save(\serialize($carriers), $key, [CacheTagEnum::Carriers->value], self::CACHE_LIFETIME);

        return $this->loaded[$key] = $carriers;
    }

    /**
     * @return array<string, Carrier>|null null when nothing usable is cached
     */
    private function readCache(string $key): ?array
    {
        $cached = $this->polkurierCache->load($key);
        if (!\is_string($cached) || $cached === '') {
            return null;
        }
        $carriers = $this->unserializeCached($cached);
        if (\is_array($carriers)
            && \array_filter($carriers, static fn(mixed $carrier): bool => !$carrier instanceof Carrier) === []
        ) {
            return $carriers;
        }
        // Unreadable, whatever the reason: fetched again.
        $this->polkurierCache->remove($key);

        return null;
    }

    /**
     * A cached value, or false when it can't be read back whole.
     */
    private function unserializeCached(string $cached): mixed
    {
        try {
            $classes = $this->cachedClasses($cached);

            return $classes === null ? false : \unserialize($cached, ['allowed_classes' => $classes]);
        } catch (\Throwable) {
            return false;
        }
    }

    private function pointsFailureKey(): string
    {
        return $this->accountCache->key('points');
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
        return $this->accountCache->key('carriers', $this->sdkVersion());
    }

    /**
     * Cached lists of an earlier SDK may no longer fit its classes.
     */
    private function sdkVersion(): string
    {
        try {
            return InstalledVersions::getVersion(self::SDK_PACKAGE)
                . '@' . InstalledVersions::getReference(self::SDK_PACKAGE);
        } catch (\OutOfBoundsException) {
            return '';
        }
    }
}
