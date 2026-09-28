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
 * Takes the grid selection to the collective pickup form.
 */
class MassPickup extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function execute(): Redirect
    {
        try {
            $ids = \array_map(static fn($shipment): int => (int)$shipment->getId(), $this->getSelectedShipments());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $ids = [];
        }
        if ($ids === []) {
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/pickupForm', ['ids' => \implode(',', $ids)]);
    }
}
