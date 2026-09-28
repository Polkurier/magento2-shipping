<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Checkout;

use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\Data\CartInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\QuotePointFactory;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Psr\Log\LoggerInterface;

/**
 * Records the pickup point a customer chose, after checking it with Polkurier.
 *
 * What the browser says about the point is not trusted: the point is looked up by its id, and its
 * label and whether it accepts cash on delivery come from that lookup. If Polkurier cannot be
 * asked, the id is kept, labelled with itself, and treated as not accepting cash on delivery.
 */
class PointSelection
{
    public function __construct(
        private readonly OrderPointStore $pointStore,
        private readonly QuotePointFactory $quotePointFactory,
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param int $quoteAddressId the shipping address in multi-address checkout, else 0
     * @throws InputException when the method needs a point and none, or an unknown one, was given
     */
    public function select(CartInterface $quote, string $carrierCode, string $methodCode, ?string $pointId, int $quoteAddressId = 0): void
    {
        $quoteId = (int)$quote->getId();
        $storeId = (int)$quote->getStoreId();
        $pointId = $pointId !== null ? \trim($pointId) : '';
        if ($carrierCode !== Polkurier::CODE) {
            $this->pointStore->deleteForQuote($quoteId, $quoteAddressId);

            return;
        }

        try {
            $carrier = $this->catalog->getCarrier($methodCode, $storeId);
        } catch (PolkurierException $e) {
            $this->logger->warning('Polkurier carrier list unavailable while saving the pickup point', ['exception' => $e]);
            $carrier = null;
        }
        $required = $carrier !== null && $this->features->isRecipientPointRequired($carrier);
        $allowed = $carrier === null || $this->features->isRecipientPointAllowed($carrier);

        if ($pointId === '' || !$allowed) {
            if ($required) {
                throw new InputException(\__('Choose a pickup point for the selected delivery method.'));
            }
            $this->pointStore->deleteForQuote($quoteId, $quoteAddressId);

            return;
        }

        $label = $pointId;
        $cod = false;
        $providers = $carrier !== null ? $this->features->getPointProviders($carrier) : [];
        if ($providers !== []) {
            try {
                $point = $this->catalog->findPoint($pointId, $providers, $storeId);
                if ($point === null) {
                    throw new InputException(\__('The chosen pickup point is not available. Choose another one.'));
                }
                // The same as the map shows after choosing: the address, the point's id beside it.
                $label = $point->getAddress() ?: \trim($point->getStreet() . ' ' . $point->getZip() . ' ' . $point->getCity());
                $cod = $point->isCod();
            } catch (PolkurierException $e) {
                $this->logger->warning('Polkurier pickup point lookup failed', ['exception' => $e]);
            }
        }

        $point = $this->pointStore->getForQuote($quoteId, $quoteAddressId);
        if ($point === null) {
            $point = $this->quotePointFactory->create()->setQuoteId($quoteId)->setQuoteAddressId($quoteAddressId);
            $point->isObjectNew(true);
        }
        $point->setMethodCode($methodCode)
            ->setPointId($pointId)
            ->setPointLabel(\mb_substr($label, 0, 512))
            ->setPointCod($cod);
        $this->pointStore->saveForQuote($point);
    }

    /**
     * Refuses to go on when the chosen method needs a pickup point and none is stored for it.
     * When the carrier list cannot be read, the order is let through rather than lost.
     *
     * @param string $methodCode the method code after "polkurier_"
     * @throws InputException
     */
    public function requirePoint(CartInterface $quote, string $methodCode, int $quoteAddressId = 0): void
    {
        $point = $this->pointStore->getForQuote((int)$quote->getId(), $quoteAddressId);
        if ($point !== null && $point->getMethodCode() === $methodCode) {
            return;
        }
        try {
            $carrier = $this->catalog->getCarrier($methodCode, (int)$quote->getStoreId());
        } catch (PolkurierException) {
            return;
        }
        if ($carrier !== null && $this->features->isRecipientPointRequired($carrier)) {
            throw new InputException(\__('Choose a pickup point for the selected delivery method.'));
        }
    }
}
