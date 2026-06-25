<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

readonly class SaveShipmentDataObserver implements ObserverInterface
{
    /**
     * @param RequestInterface $request
     */
    public function __construct(
        private RequestInterface $request,
    ) {}

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $shipment = $observer->getEvent()->getShipment();
        $shipmentPostData = $this->request->getParam('shipment');

//        if (isset($shipmentPostData['custom_insurance_value'])) {
//            // Ensure you have created this custom column in the sales_shipment table via db_schema.xml
//            $shipment->setData('custom_insurance_value', $shipmentPostData['custom_insurance_value']);
//        }
    }
}
