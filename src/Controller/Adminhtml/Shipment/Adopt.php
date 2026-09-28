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
 * Links a shipment whose outcome is unknown to the Polkurier order the merchant names.
 */
class Adopt extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function execute(): Redirect
    {
        $shipment = $this->getShipment();
        $number = \trim((string)$this->getRequest()->getParam('polkurier_number'));
        if ($shipment !== null && $number !== '') {
            try {
                $shipment = $this->management->adopt((int)$shipment->getId(), $number);
                $this->messageManager->addSuccessMessage(\__('Polkurier order %1 linked.', $shipment->getPolkurierNumber()));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->back();
    }
}
