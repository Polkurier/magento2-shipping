<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Psr\Log\LoggerInterface;

/**
 * Hides cash-on-delivery payment when the chosen Polkurier carrier, or the chosen pickup point,
 * cannot collect it. When Polkurier cannot be asked, payment is left as it is.
 *
 * Event: payment_method_is_active
 */
readonly class RestrictCashOnDeliveryObserver implements ObserverInterface
{
    public function __construct(
        private Config $config,
        private CarrierCatalog $catalog,
        private CarrierFeatures $features,
        private OrderPointStore $pointStore,
        private LoggerInterface $logger,
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
        if (!\in_array($method->getCode(), $this->config->getCodPaymentMethods($storeId), true)) {
            return;
        }
        if (!$quote instanceof \Magento\Quote\Model\Quote) {
            return;
        }
        foreach ($this->getDeliveries($quote) as [$carrierCode, $quoteAddressId]) {
            if (!$this->canCollect($quote, $carrierCode, $quoteAddressId)) {
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

    private function canCollect(\Magento\Quote\Model\Quote $quote, string $carrierCode, int $quoteAddressId): bool
    {
        try {
            $carrier = $this->catalog->getCarrier($carrierCode);
        } catch (PolkurierException $e) {
            $this->logger->warning('Polkurier carrier list unavailable for cash on delivery', ['exception' => $e]);

            return true;
        }
        $point = $this->pointStore->getForQuote((int)$quote->getId(), $quoteAddressId);

        return $carrier !== null
            && $this->features->isCodAvailable($carrier)
            && ($point === null || $point->getMethodCode() !== $carrierCode || $point->isPointCod());
    }
}
