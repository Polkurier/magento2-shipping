<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * Reads the current state of the selected placed Polkurier orders.
 */
class MassRefresh extends AbstractShipmentAction implements HttpPostActionInterface
{
    public function execute(): Redirect
    {
        $refreshed = 0;
        try {
            foreach ($this->getSelectedShipments() as $shipment) {
                if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
                    continue;
                }
                try {
                    $this->management->refreshStatus((int)$shipment->getId());
                    $refreshed++;
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(\__('%1: %2', $shipment->getPolkurierNumber(), $e->getMessage()));
                }
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        $this->messageManager->addSuccessMessage(\__('%1 shipment(s) updated.', $refreshed));

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
