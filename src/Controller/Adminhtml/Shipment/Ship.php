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
 * Creates the Magento shipment of a placed Polkurier order when that failed at placing.
 */
class Ship extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function execute(): Redirect
    {
        $shipment = $this->getShipment();
        if ($shipment !== null) {
            try {
                $shipment = $this->management->shipOrder((int)$shipment->getId());
                $this->messageManager->addSuccessMessage(\__('The order is shipped with Polkurier order %1.', (string)$shipment->getPolkurierNumber()));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->back();
    }
}
