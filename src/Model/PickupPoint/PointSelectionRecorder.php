<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PickupPoint;

use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\Data\CartInterface;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\QuotePointFactory;
use Polkurier\Webservice\Enum\Courier\PointFunctionEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\CourierPoint;

/**
 * Records the pickup point a customer chose, after checking it with Polkurier.
 *
 * What the browser says about the point is not trusted. The point is looked up by its id, and has
 * to be open and do what the map shows points for; its label and whether it accepts cash on
 * delivery come from that lookup. If Polkurier cannot be asked, the id is kept and used as its own
 * label, and whether it accepts cash on delivery stays unknown, so it's taken to accept it.
 */
readonly class PointSelectionRecorder
{
    public function __construct(
        private QuotePointRepositoryInterface $quotePointRepository,
        private QuotePointFactory $quotePointFactory,
        private CarrierCatalog $carrierCatalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private ConfigProvider $configProvider,
        private Logger $logger,
    ) {}

    /**
     * @param int $quoteAddressId the shipping address in multi-address checkout, else 0
     *
     * @throws InputException when the method needs a point and none, or an unknown one, was given
     */
    public function select(
        CartInterface $quote,
        string $carrierCode,
        string $methodCode,
        ?string $pointId,
        int $quoteAddressId = 0,
    ): void {
        $quoteId = (int)$quote->getId();
        $storeId = (int)$quote->getStoreId();
        $pointId = $pointId !== null ? \trim($pointId) : '';
        if ($carrierCode !== Polkurier::CODE) {
            $this->quotePointRepository->deleteForQuote($quoteId, $quoteAddressId);

            return;
        }

        try {
            $carrier = $this->carrierCatalog->getCarrier($methodCode);
        } catch (PolkurierException $e) {
            $this->logger->warning(
                'Polkurier carrier list unavailable while saving the pickup point',
                ['exception' => $e],
            );
            $carrier = null;
        }
        $required = $carrier !== null && $this->carrierFeatureResolver->isRecipientPointRequired($carrier);
        $allowed = $carrier === null || $this->carrierFeatureResolver->isRecipientPointAllowed($carrier);

        if ($pointId === '' || !$allowed) {
            if ($required) {
                throw new InputException(__('Choose a pickup point for the selected delivery method.'));
            }
            $this->quotePointRepository->deleteForQuote($quoteId, $quoteAddressId);

            return;
        }

        $label = $pointId;
        // Unknown unless Polkurier says; while unknown, cash on delivery is left as it is.
        $cod = null;
        $providers = $carrier !== null ? $this->carrierFeatureResolver->getPointProviders($carrier) : [];
        if ($providers !== []) {
            try {
                $point = $this->carrierCatalog->findPoint($pointId, $providers);
                if ($point === null || !$this->isUsable($point, $storeId)) {
                    throw new InputException(__('The chosen pickup point is not available. Choose another one.'));
                }
                // The same as the map shows after choosing: the address, the point's id beside it.
                $town = \trim($point->getZip() . ' ' . $point->getCity());
                $label = $point->getAddress()
                    ?: \implode(', ', \array_filter([\trim((string)$point->getStreet()), $town]));
                $cod = $point->isCod();
            } catch (RecentFailureException) {
                // Logged when it failed.
            } catch (PolkurierException $e) {
                $this->logger->warning('Polkurier pickup point lookup failed', ['exception' => $e]);
            }
        }

        $point = $this->quotePointRepository->getForQuote($quoteId, $quoteAddressId)
            ?? $this->quotePointFactory->create()->setQuoteId($quoteId)->setQuoteAddressId($quoteAddressId);
        $point->setMethodCode($methodCode)
            ->setPointId($pointId)
            ->setPointLabel(\mb_substr($label, 0, 512))
            ->setPointCod($cod);
        $this->quotePointRepository->save($point);
    }

    /**
     * Refuses to go on when the chosen method needs a pickup point and none is stored for it.
     * When the carrier list cannot be read, the order is let through rather than lost.
     *
     * @param string $methodCode the method code after "polkurier_"
     *
     * @throws InputException
     */
    public function requirePoint(CartInterface $quote, string $methodCode, int $quoteAddressId = 0): void
    {
        $point = $this->quotePointRepository->getForQuote((int)$quote->getId(), $quoteAddressId);
        if ($point !== null && $point->getMethodCode() === $methodCode) {
            return;
        }
        try {
            $carrier = $this->carrierCatalog->getCarrier($methodCode);
        } catch (PolkurierException) {
            return;
        }
        if ($carrier !== null && $this->carrierFeatureResolver->isRecipientPointRequired($carrier)) {
            throw new InputException(__('Choose a pickup point for the selected delivery method.'));
        }
    }

    /**
     * Whether the point is open and does everything the map is set to show points for, such as
     * handing out parcels.
     */
    private function isUsable(CourierPoint $point, int $storeId): bool
    {
        if (!$point->isAvailable()) {
            return false;
        }
        $functions = \array_map(
            static fn(PointFunctionEnum $function): string => $function->value,
            $point->getFunctions(),
        );
        foreach ($this->configProvider->getMapPointFunctions($storeId) as $required) {
            if (!\in_array($required->value, $functions, true)) {
                return false;
            }
        }

        return true;
    }
}
