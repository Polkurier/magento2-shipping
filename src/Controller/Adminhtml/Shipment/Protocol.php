<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Downloads the protocol of one shipment (by id) or of the shipments selected in a grid, as one file.
 */
class Protocol extends AbstractShipmentAction implements HttpGetActionInterface, HttpPostActionInterface
{
    public function execute(): ResponseInterface|ResultInterface
    {
        try {
            return $this->download($this->documents->getProtocol($this->getSelectedShipments()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->back();
    }
}
