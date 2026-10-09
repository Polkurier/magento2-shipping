<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer\Checkout;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Turns cash-on-delivery payment off when the chosen Polkurier carrier, or the chosen pickup point,
 * cannot collect it. When Polkurier cannot be asked, or doesn't say whether the point takes cash,
 * payment is left as it is.
 *
 * Event: payment_method_is_active
 */
readonly class RestrictCashOnDeliveryObserver implements ObserverInterface
{
    public function __construct(
        private ConfigProvider $configProvider,
        private CarrierCatalog $carrierCatalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private QuotePointRepositoryInterface $quotePointRepository,
        private Logger $logger,
    ) {}

    public function execute(Observer $observer): void
    {
        /** @var DataObject $result */
        $result = $observer->getEvent()->getData('result');
        /** @var MethodInterface $method */
        $method = $observer->getEvent()->getData('method_instance');
        /** @var CartInterface|null $quote */
        $quote = $observer->getEvent()->getData('quote');
        if ($quote === null || !$result->getData('is_available')) {
            return;
        }
        $storeId = (int)$quote->getStoreId();
        if (!\in_array($method->getCode(), $this->configProvider->getCodPaymentMethods($storeId), true)) {
            return;
        }
        if (!$quote instanceof \Magento\Quote\Model\Quote) {
            return;
        }
        foreach ($this->getDeliveries($quote) as [$carrierCode, $quoteAddressId]) {
            if (!$this->collectsCod($quote, $carrierCode, $quoteAddressId)) {
                $result->setData('is_available', false);

                return;
            }
        }
    }

    /**
     * The Polkurier deliveries of the cart: one, or one per address in multi-address checkout.
     *
     * @return array<int, array{string, int}> carrier code and quote address id (0 in the regular checkout)
     */
    private function getDeliveries(\Magento\Quote\Model\Quote $quote): array
    {
        if (!$quote->getIsMultiShipping()) {
            $code = Polkurier::methodCodeOf((string)$quote->getShippingAddress()->getShippingMethod());

            return $code !== null ? [[$code, 0]] : [];
        }
        $deliveries = [];
        foreach ($quote->getAllShippingAddresses() as $address) {
            $code = Polkurier::methodCodeOf((string)$address->getShippingMethod());
            if ($code !== null) {
                $deliveries[] = [$code, (int)$address->getId()];
            }
        }

        return $deliveries;
    }

    /**
     * Whether the delivery's carrier, and its pickup point if any, collect cash on delivery.
     */
    private function collectsCod(\Magento\Quote\Model\Quote $quote, string $carrierCode, int $quoteAddressId): bool
    {
        try {
            $carrier = $this->carrierCatalog->getCarrier($carrierCode);
        } catch (PolkurierException $e) {
            $this->logger->warning('Polkurier carrier list unavailable for cash on delivery', ['exception' => $e]);

            return true;
        }
        if ($carrier === null || !$this->carrierFeatureResolver->isCodAvailable($carrier)) {
            return false;
        }
        $point = $this->quotePointRepository->getForQuote((int)$quote->getId(), $quoteAddressId);

        // When it's not known whether the point takes cash, assume it does.
        return $point === null || $point->getMethodCode() !== $carrierCode || $point->getPointCod() !== false;
    }
}
