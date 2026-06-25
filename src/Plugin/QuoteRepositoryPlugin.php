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
use Polkurier\Shipping\Api\Data\Quote\ShipmentDataInterfaceFactory;
use Polkurier\Shipping\Helper\ShipmentHelper;
use Polkurier\Shipping\Model\ResourceModel\Quote\ShipmentData as ShipmentDataResource;

readonly class QuoteRepositoryPlugin
{
    /**
     * @param CartExtensionFactory $extensionFactory
     * @param ShipmentDataInterfaceFactory $shipmentDataFactory
     * @param ShipmentDataResource $shipmentDataResource
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
    ) {}

    /**
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     *
     * @return CartInterface
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
        if (ShipmentHelper::isQuotePolkurierShipment($quote)
            && $shipmentData = $quote->getExtensionAttributes()?->getPolkurierShipmentData()
        ) {
            $shipmentData->setQuoteId($quote->getId());
            $this->shipmentDataResource->save($shipmentData);
        }

        return [$quote];
    }

    /**
     * @param CartInterface $quote
     *
     * @return CartInterface
     */
    private function loadExtensionAttributes(CartInterface $quote): CartInterface
    {
        $extensionAttributes = $quote->getExtensionAttributes() ?: $this->extensionFactory->create();
        $shipmentData = $this->shipmentDataFactory->create();
        if (ShipmentHelper::isQuotePolkurierShipment($quote)) {
            $this->shipmentDataResource->load($shipmentData, $quote->getId());
        }
        if (!$shipmentData->getId()) {
            $shipmentData->setQuoteId($quote->getId());
        }
        $extensionAttributes->setPolkurierShipmentData($shipmentData);
        $quote->setExtensionAttributes($extensionAttributes);

        return $quote;
    }
}
