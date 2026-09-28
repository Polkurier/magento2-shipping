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

/**
 * Cancels one Polkurier order.
 */
class Cancel extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_cancel';

    public function execute(): Redirect
    {
        $shipment = $this->getShipment();
        if ($shipment !== null) {
            try {
                $shipment = $this->management->cancel((int)$shipment->getId());
                $this->messageManager->addSuccessMessage(\__('Polkurier order %1 cancelled.', $shipment->getPolkurierNumber()));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->back();
    }
}
