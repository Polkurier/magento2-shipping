<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\CarrierConfig;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Notification\NotifierInterface;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Enum\CarrierConfig\PricingModelEnum;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\CarrierConfigFactory;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as CarrierConfigResource;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;

/**
 * Keeps one settings row per carrier the account offers.
 *
 * New carriers arrive switched off and last in order. A carrier the account no longer offers keeps
 * its row, with the merchant's settings, and is marked unavailable, so it disappears from checkout
 * and comes back as it was if the account offers it again.
 */
readonly class CarrierConfigSynchronizer
{
    public function __construct(
        private CarrierCatalog $carrierCatalog,
        private CarrierConfigProvider $carrierConfigProvider,
        private CarrierConfigRepositoryInterface $carrierConfigRepository,
        private CarrierConfigFactory $carrierConfigFactory,
        private CarrierConfigResource $resource,
        private ErrorMessageResolver $errorMessageResolver,
        private NotifierInterface $notifier,
        private UrlInterface $url,
    ) {}

    /**
     * @return int how many carriers the account offers
     * @throws LocalizedException when Polkurier can't be asked, as said by {@see ErrorMessageResolver}
     * @throws CouldNotSaveException
     */
    public function synchronize(): int
    {
        try {
            $carriers = $this->carrierCatalog->refreshAccountCarriers();
        } catch (PolkurierException $e) {
            throw $this->errorMessageResolver->exception($e);
        }
        $this->carrierConfigProvider->reset();
        $existing = $this->carrierConfigProvider->getAll();
        $last = \array_reduce($existing, static fn(int $max, $config): int => \max($max, $config->getSortOrder()), 0);

        // The account's carriers change over time: changes are reported, except on the first read.
        $added = [];
        $revoked = [];
        // All rows or none: a failure halfway would leave carriers neither offered nor withdrawn.
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $this->apply($carriers, $existing, $last, $added, $revoked);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        $this->carrierConfigProvider->reset();
        $this->tell($added, $revoked);

        return \count($carriers);
    }

    /**
     * @param array<string, Carrier> $carriers the account's, by code
     * @param array<string, CarrierConfigInterface> $existing the rows there are, by code
     * @param int $last the last sort order taken
     * @param string[] $added the names of carriers new to the account, added to
     * @param string[] $revoked the names of carriers the account no longer offers, added to
     *
     * @throws CouldNotSaveException
     */
    private function apply(array $carriers, array $existing, int $last, array &$added, array &$revoked): void
    {
        foreach ($carriers as $code => $carrier) {
            if ($existing !== [] && !($existing[$code] ?? null)?->isAvailable()) {
                $added[] = CarrierNameProvider::nameOr($carrier->getName(), $code);
            }
            $config = $existing[$code] ?? $this->carrierConfigFactory->create()
                ->setCarrierCode($code)
                // A new carrier goes to the end of the list, where the merchant can drag it from.
                ->setSortOrder($last += CarrierSortOrderUpdater::STEP)
                ->setActive(false)
                ->setPricingModel(PricingModelEnum::Fixed->value)
                ->setPriceInfo([]);
            $config->setCarrierName($carrier->getName());
            $config->setAvailable(true);
            $this->carrierConfigRepository->save($config);
        }
        foreach ($existing as $code => $config) {
            if (!isset($carriers[$code]) && $config->isAvailable()) {
                $this->carrierConfigRepository->save($config->setAvailable(false));
                $revoked[] = CarrierNameProvider::nameOr($config->getCarrierName(), $code);
            }
        }
    }

    /**
     * Adds to the admin's notifications the carriers the account gained, which wait switched off
     * for their prices, and those it lost, which are gone from checkout and shipping.
     *
     * @param string[] $added
     * @param string[] $revoked
     */
    private function tell(array $added, array $revoked): void
    {
        // Without the secret key: a notification outlives the admin session it was made in.
        $url = $this->url->getUrl('polkurier/carrier/index', ['_nosecret' => true]);
        if ($added !== []) {
            $this->notifier->addNotice(
                (string)__('Polkurier: new carriers on your account'),
                (string)__(
                    'Your Polkurier account now offers %1. '
                    . 'They are switched off until you set their prices and '
                    . 'switch them on under Polkurier > Settings > Carriers.',
                    \implode(', ', $added),
                ),
                $url,
            );
        }
        if ($revoked !== []) {
            $this->notifier->addMajor(
                (string)__('Polkurier: carriers no longer on your account'),
                (string)__(
                    'Your Polkurier account no longer offers %1. They are gone from checkout and shipping; '
                    . 'orders placed with them need another carrier to ship.',
                    \implode(', ', $revoked),
                ),
                $url,
            );
        }
    }
}
