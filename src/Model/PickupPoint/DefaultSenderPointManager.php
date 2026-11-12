<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PickupPoint;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\Cache\CacheTagEnum;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\Account\ChangeDefaultSenderPointMessage;
use Polkurier\Webservice\Response\Carrier;

/**
 * The account's default drop-off point for each carrier. Polkurier keeps one per point network,
 * shared by its carriers, and refuses a point of another network. Read once per request; when
 * Polkurier can't be asked, there are none, and it isn't asked again for a moment.
 */
class DefaultSenderPointManager implements ResetAfterRequestInterface
{
    public const int FAILURE_LIFETIME = 90;

    /**
     * The points read in this request.
     *
     * @var array<string, string>|null
     */
    private ?array $points = null;

    public function __construct(
        private readonly PolkurierClientInterface $polkurierClient,
        private readonly CarrierFeatureResolver $carrierFeatureResolver,
        private readonly PolkurierCache $polkurierCache,
        private readonly AccountCache $accountCache,
        private readonly ErrorMessageResolver $errorMessageResolver,
        private readonly Logger $logger,
    ) {}

    /**
     * @return array<string, string> point codes by carrier code, '' for a carrier with none set;
     *     the carriers Polkurier keeps no default for are left out
     */
    public function get(): array
    {
        if ($this->points !== null) {
            return $this->points;
        }
        $failureKey = $this->accountCache->key('default_sender_points_failed');
        if ($this->polkurierCache->load($failureKey)) {
            return $this->points = [];
        }
        try {
            $points = $this->polkurierClient->account()->getAccountInfo()->getDefaultSenderPoint();
        } catch (PolkurierException $e) {
            $this->logger->warning('Default drop-off points unavailable', ['exception' => $e]);
            $this->polkurierCache->save('1', $failureKey, [CacheTagEnum::Failures->value], self::FAILURE_LIFETIME);

            return $this->points = [];
        }

        return $this->points = \array_map(static fn(?string $point): string => \trim((string)$point), $points);
    }

    /**
     * The default drop-off point of a carrier that takes parcels at points: '' when none is set,
     * null when the carrier takes none or Polkurier keeps no default for it.
     */
    public function getForCarrier(Carrier $carrier): ?string
    {
        if ($this->carrierFeatureResolver->getSenderPointRequirement($carrier) === PointRequirementEnum::None) {
            return null;
        }

        return $this->get()[$carrier->getServiceCode()] ?? null;
    }

    /**
     * Sets the carrier's default drop-off point, or clears it with ''.
     *
     * @throws LocalizedException with Polkurier's reason when it refuses or can't be asked
     */
    public function change(string $carrierCode, string $pointId): void
    {
        try {
            $this->polkurierClient->account()->changeDefaultSenderPoint(
                new ChangeDefaultSenderPointMessage($carrierCode, $pointId),
            );
        } catch (PolkurierException $e) {
            throw $this->errorMessageResolver->exception($e);
        }
        // The other carriers of the network have changed too.
        $this->points = null;
    }

    public function _resetState(): void
    {
        $this->points = null;
    }
}
