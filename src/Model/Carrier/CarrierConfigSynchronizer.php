<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Notification\NotifierInterface;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Model\CarrierConfigFactory;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Pricing\PricingModelEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Keeps one settings row per carrier the account offers.
 *
 * New carriers arrive switched off and last in order. A carrier the account no longer offers keeps
 * its row, with the merchant's settings, and is marked unavailable, so it disappears from checkout
 * and comes back as it was if the account offers it again.
 */
class CarrierConfigSynchronizer
{
    public function __construct(
        private readonly CarrierCatalog $catalog,
        private readonly CarrierConfigs $carrierConfigs,
        private readonly CarrierConfigRepositoryInterface $repository,
        private readonly CarrierConfigFactory $configFactory,
        private readonly ErrorMessages $errorMessages,
        private readonly NotifierInterface $notifier,
        private readonly UrlInterface $url,
    ) {}

    /**
     * @return int how many carriers the account offers
     * @throws LocalizedException when Polkurier can't be asked, as said by ErrorMessages
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function synchronize(): int
    {
        $this->catalog->clean();
        $this->carrierConfigs->reset();
        try {
            $carriers = $this->catalog->getAccountCarriers();
        } catch (PolkurierException $e) {
            throw $this->errorMessages->exception($e);
        }
        $existing = $this->carrierConfigs->getAll();
        $last = \array_reduce($existing, static fn(int $max, $config): int => \max($max, $config->getSortOrder()), 0);

        // The account's carriers change over time: they're told about, except on the first read.
        $added = [];
        $revoked = [];
        foreach ($carriers as $code => $carrier) {
            if ($existing !== [] && !($existing[$code] ?? null)?->isAvailable()) {
                $added[] = $carrier->getName() !== '' ? $carrier->getName() : (string)$code;
            }
            $config = $existing[$code] ?? $this->configFactory->create()
                ->setCarrierCode($code)
                // A new carrier goes to the end of the list, where the merchant can drag it from.
                ->setSortOrder($last += CarrierOrder::STEP)
                ->setActive(false)
                ->setPricingModel(PricingModelEnum::Fixed->value)
                ->setPriceInfo([]);
            $config->setCarrierName($carrier->getName());
            $config->setAvailable(true);
            $this->repository->save($config);
        }
        foreach ($existing as $code => $config) {
            if (!isset($carriers[$code]) && $config->isAvailable()) {
                $this->repository->save($config->setAvailable(false));
                $revoked[] = $config->getCarrierName() ?: (string)$code;
            }
        }
        $this->carrierConfigs->reset();
        $this->tell($added, $revoked);

        return \count($carriers);
    }

    /**
     * Into the admin's notifications: carriers the account gained, which wait switched off for
     * their prices, and those it lost, which are gone from checkout and shipping.
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
                (string)\__('Polkurier: new carriers on your account'),
                (string)\__('Your Polkurier account now offers %1. They are switched off until you set their prices and switch them on under Polkurier > Settings > Carriers.', \implode(', ', $added)),
                $url,
            );
        }
        if ($revoked !== []) {
            $this->notifier->addMajor(
                (string)\__('Polkurier: carriers no longer on your account'),
                (string)\__('Your Polkurier account no longer offers %1. They are gone from checkout and shipping; orders placed with them need another carrier to ship.', \implode(', ', $revoked)),
                $url,
            );
        }
    }
}
