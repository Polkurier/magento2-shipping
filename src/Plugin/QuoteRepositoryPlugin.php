<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartSearchResultsInterface;
use Polkurier\Shipping\Api\Data\Quote\ShipmentDataInterface;
use Polkurier\Shipping\Api\Data\Quote\ShipmentDataInterfaceFactory;
use Polkurier\Shipping\Helper\ShipmentHelper;
use Polkurier\Shipping\Model\ResourceModel\Quote\ShipmentData as ShipmentDataResource;

readonly class QuoteRepositoryPlugin
{
    /**
     * @param CartExtensionFactory $extensionFactory
     * @param ShipmentDataInterfaceFactory $shipmentDataFactory
     * @param ShipmentDataResource $shipmentDataResource
     * @param ShipmentHelper $shipmentHelper
     */
    public function __construct(
        /**
         * @var CartExtensionFactory
         */
        private CartExtensionFactory $extensionFactory,

        /**
         * @var ShipmentDataInterfaceFactory
         */
        private ShipmentDataInterfaceFactory $shipmentDataFactory,

        /**
         * @var ShipmentDataResource
         */
        private ShipmentDataResource $shipmentDataResource,

        /**
         * @var ShipmentHelper
         */
        private ShipmentHelper $shipmentHelper,
    ) {}

    /**
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     *
     * @return CartInterface
     * @throws AlreadyExistsException
     */
    public function afterGet(CartRepositoryInterface $subject, CartInterface $quote): CartInterface
    {
        return $this->loadExtensionAttributes($quote);
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     *
     * @return CartInterface
     * @throws AlreadyExistsException
     */
    public function afterGetActive(CartRepositoryInterface $subject, CartInterface $quote): CartInterface
    {
        return $this->loadExtensionAttributes($quote);
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     *
     * @return CartInterface
     * @throws AlreadyExistsException
     */
    public function afterGetForCustomer(CartRepositoryInterface $subject, CartInterface $quote): CartInterface
    {
        return $this->loadExtensionAttributes($quote);
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     *
     * @return CartInterface
     * @throws AlreadyExistsException
     */
    public function afterGetActiveForCustomer(CartRepositoryInterface $subject, CartInterface $quote): CartInterface
    {
        return $this->loadExtensionAttributes($quote);
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param CartSearchResultsInterface $results
     *
     * @return CartSearchResultsInterface
     * @throws AlreadyExistsException
     */
    public function afterGetList(
        CartRepositoryInterface $subject,
        CartSearchResultsInterface $results
    ): CartSearchResultsInterface {
        foreach ($results->getItems() as $quote) {
            $this->loadExtensionAttributes($quote);
        }

        return $results;
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     *
     * @return array
     * @throws AlreadyExistsException
     */
    public function beforeSave(CartRepositoryInterface $subject, CartInterface $quote): array
    {
        if (!$this->shipmentHelper->isQuotePolkurierShipment($quote)) {
            return [$quote];
        }
        $this->shipmentDataResource->save(
            $quote->getExtensionAttributes()?->getPolkurierShipmentData()
                ?? $this->initPolkurierShipmentData($quote)
        );

        return [$quote];
    }

    /**
     * @param CartInterface $quote
     *
     * @return CartInterface
     * @throws AlreadyExistsException
     */
    private function loadExtensionAttributes(CartInterface $quote): CartInterface
    {
        if (!$this->shipmentHelper->isQuotePolkurierShipment($quote)) {
            return $quote;
        }
        $extensionAttributes = $quote->getExtensionAttributes() ?: $this->extensionFactory->create();
        $extensionAttributes->setPolkurierShipmentData(
            $extensionAttributes->getPolkurierShipmentData()
                ?: $this->initPolkurierShipmentData($quote)
        );
        $quote->setExtensionAttributes($extensionAttributes);

        return $quote;
    }

    /**
     * @param CartInterface $quote
     *
     * @return ShipmentDataInterface
     * @throws AlreadyExistsException
     */
    private function initPolkurierShipmentData(CartInterface $quote): ShipmentDataInterface
    {
        $carrierCode = $this->shipmentHelper->getCarrierCode($quote);
        $shipmentData = $this->shipmentDataFactory->create();
        $this->shipmentDataResource->load($shipmentData, $quote->getId());
        if (!$shipmentData->getId()) {
            $shipmentData->isObjectNew(true);
            $shipmentData->setQuoteId((int)$quote->getId());
        }
        $shipmentData->setCarrierCode($carrierCode);
        $shipmentData->setCodAvailable($this->shipmentHelper->isCodAvailableForCarrier($carrierCode));
        $this->shipmentDataResource->save($shipmentData);

        return $shipmentData;
    }
}
