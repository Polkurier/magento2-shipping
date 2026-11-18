<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\SdkVersionProvider;
use Polkurier\Webservice\Dto\Request\Courier\AvailableCarriersMessage;
use Polkurier\Webservice\Dto\Response\Carrier;
use Polkurier\Webservice\Dto\Response\CourierPoint;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The carriers the Polkurier account may ship with, and what each supports.
 *
 * The list is account-specific but changes rarely, and checkout needs it on every cart change, so
 * it's cached per account for a day; {@see \Polkurier\Shipping\Cron\RefreshCarriersTask} refreshes
 * it daily. The cache holds the carriers as the SDK's serializer writes them, and the SDK reads them
 * back. The key changes with the SDK version, and an entry that can't be read back is dropped and
 * fetched again.
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
    /**
     * How long the carrier list is cached.
     */
    public const int CACHE_LIFETIME = 86400;

    /**
     * How long a looked-up point is cached, whether it was found or not.
     */
    public const int POINT_LIFETIME = 86400;

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
        private readonly Json $json,
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
        $key = $this->getCacheKey();
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
        $failure = $this->polkurierCache->loadFailure($key);
        if ($failure !== null) {
            throw $this->failed[$key] = new RecentFailureException($failure);
        }

        try {
            $carriers = $this->fetch();
        } catch (PolkurierException $e) {
            $this->polkurierCache->saveFailure(
                $key,
                (string)$this->errorMessageResolver->createPhrase($e),
                CacheTagEnum::Carriers,
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

        return $this->keep($this->getCacheKey(), $carriers);
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
        $key = $this->accountCache->buildKey(
            'point',
            SdkVersionProvider::getInstalledVersion(),
            $pointId,
            \implode(',', $providers),
        );
        $cached = $this->polkurierCache->load($key);
        if ($cached === self::NO_POINT) {
            return null;
        }
        if (\is_string($cached) && $cached !== '') {
            try {
                return $this->polkurierClient->getSerializer()->decode($cached, CourierPoint::class);
            } catch (PolkurierException) {
                // Unreadable, whatever the reason: looked up again.
                $this->polkurierCache->remove($key);
            }
        }
        foreach ([$this->getCacheKey(), $this->getPointsFailureKey()] as $failing) {
            $failure = $this->failed[$failing] ?? $this->polkurierCache->loadFailure($failing);
            if ($failure instanceof PolkurierException) {
                throw $failure;
            }
            if ($failure !== null) {
                throw new RecentFailureException($failure);
            }
        }

        try {
            $point = $this->polkurierClient->getCourier()->getCourierPoint($pointId, $providers);
        } catch (PolkurierException $e) {
            // Polkurier unreachable or busy: not asked again for a while. A refusal of this point
            // says nothing of the next one.
            if ($this->errorMessageResolver->isTransient($e)) {
                $this->polkurierCache->saveFailure(
                    $this->getPointsFailureKey(),
                    (string)$this->errorMessageResolver->createPhrase($e),
                    CacheTagEnum::Points,
                );
                $this->failed[$this->getPointsFailureKey()] = $e;
            }

            throw $e;
        }
        try {
            $data = $point === null ? self::NO_POINT : $this->polkurierClient->getSerializer()->encode($point);
            $this->polkurierCache->save($data, $key, [CacheTagEnum::Points->value], self::POINT_LIFETIME);
        } catch (PolkurierException) {
            // Not cached: looked up again next time.
        }

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
        $available = $this->polkurierClient->getCourier()->getAvailableCarriers($message);
        foreach ($available as $carrier) {
            $carriers[$carrier->serviceCode] = $carrier;
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
        $serializer = $this->polkurierClient->getSerializer();
        try {
            $payloads = \array_map(static fn(Carrier $carrier): string => $serializer->encode($carrier), $carriers);
            $this->polkurierCache->save(
                (string)$this->json->serialize($payloads),
                $key,
                [CacheTagEnum::Carriers->value],
                self::CACHE_LIFETIME,
            );
        } catch (PolkurierException) {
            // Not cached: Polkurier is asked again next time.
        }

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
        $serializer = $this->polkurierClient->getSerializer();
        try {
            $carriers = [];
            foreach ((array)$this->json->unserialize($cached) as $code => $payload) {
                $carriers[(string)$code] = $serializer->decode((string)$payload, Carrier::class);
            }

            return $carriers;
        } catch (\InvalidArgumentException | PolkurierException) {
            // Unreadable, whatever the reason: fetched again.
            $this->polkurierCache->remove($key);

            return null;
        }
    }

    private function getPointsFailureKey(): string
    {
        return $this->accountCache->buildKey('points');
    }

    private function getCacheKey(): string
    {
        return $this->accountCache->buildKey('carriers', SdkVersionProvider::getInstalledVersion());
    }
}
