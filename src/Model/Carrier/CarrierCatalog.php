<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Framework\App\CacheInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Enum\Courier\AdditionalFieldTypeEnum;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\Courier\AvailableCarriersMessage;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\CourierPoint;

/**
 * The carriers the Polkurier account may ship with, and what each supports.
 *
 * The list is account-specific but changes rarely, and checkout needs it on every cart change, so
 * it's cached per account. The response classes only map one way (from
 * the API), so the cache holds them PHP-serialized, restricted to exactly these classes.
 */
class CarrierCatalog
{
    public const string CACHE_TAG = 'POLKURIER_CARRIERS';
    public const int CACHE_LIFETIME = 3600;

    /**
     * Everything a serialized carrier list may contain; nothing else is instantiated on read.
     */
    private const array CACHED_CLASSES = [
        Carrier::class,
        AdditionalData::class,
        AdditionalData\AdditionalField::class,
        AdditionalData\Cod::class,
        AdditionalData\CourierService::class,
        AdditionalData\Option::class,
        AdditionalData\Pickup::class,
        AdditionalData\PointsMap::class,
        AdditionalData\Property::class,
        AdditionalData\ShipmentType::class,
        AdditionalFieldTypeEnum::class,
        PointRequirementEnum::class,
    ];

    /**
     * @var array<string, array<string, Carrier>>
     */
    private array $loaded = [];

    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly Config $config,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * The carriers the merchant works with: the account's carriers, narrowed to the available
     * carriers set in the configuration, if any. Everything that offers carriers uses this list.
     *
     * @return array<string, Carrier> keyed by service code
     * @throws PolkurierException when the list is not cached and the API cannot be asked
     */
    public function getCarriers(?int $storeId = null): array
    {
        $carriers = $this->getAccountCarriers($storeId);
        $available = $this->config->getAvailableCarriers($storeId);

        return $available === [] ? $carriers : \array_intersect_key($carriers, \array_flip($available));
    }

    /**
     * Every carrier of the account, whatever the configuration narrows it to.
     *
     * @return array<string, Carrier> keyed by service code
     * @throws PolkurierException when the list is not cached and the API cannot be asked
     */
    public function getAccountCarriers(?int $storeId = null): array
    {
        $key = $this->cacheKey($storeId);
        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }

        $cached = $this->cache->load($key);
        if (\is_string($cached) && $cached !== '') {
            $carriers = \unserialize($cached, ['allowed_classes' => self::CACHED_CLASSES]);
            if (\is_array($carriers)) {
                return $this->loaded[$key] = $carriers;
            }
        }

        $carriers = [];
        foreach ($this->clientPool->courier($storeId)->availableCarriers(
            new AvailableCarriersMessage(additionalData: true),
        ) as $carrier) {
            $carriers[$carrier->getServiceCode()] = $carrier;
        }
        $this->cache->save(\serialize($carriers), $key, [self::CACHE_TAG], self::CACHE_LIFETIME);

        return $this->loaded[$key] = $carriers;
    }

    /**
     * @throws PolkurierException
     */
    public function getCarrier(string $code, ?int $storeId = null): ?Carrier
    {
        return $this->getCarriers($storeId)[$code] ?? null;
    }

    /**
     * Looks a pickup point up by its id, among the given map providers. Not cached: it confirms a
     * point still exists at the moment it is chosen.
     *
     * @param string[] $providers
     * @throws PolkurierException
     */
    public function findPoint(string $pointId, array $providers, ?int $storeId = null): ?CourierPoint
    {
        if ($providers === []) {
            return null;
        }

        return $this->clientPool->courier($storeId)->getCourierPoint($pointId, $providers);
    }

    /**
     * Forgets the cached lists of every account, e.g. after the credentials changed.
     */
    public function clean(): void
    {
        $this->loaded = [];
        $this->cache->clean([self::CACHE_TAG]);
    }

    private function cacheKey(?int $storeId): string
    {
        return 'polkurier_carriers_' . \sha1(
            $this->config->getLogin($storeId) . '|' . (int)$this->config->isSandbox($storeId),
        );
    }
}
