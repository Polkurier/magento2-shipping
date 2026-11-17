<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\ShipmentManagementInterface;

/**
 * Cancels one Polkurier order.
 */
class Cancel extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_cancel';
    public const string ROUTE = 'polkurier/shipment/cancel';

    public function __construct(
        Context $context,
        RequestedShipmentResolver $requestedShipmentResolver,
        private readonly ShipmentManagementInterface $shipmentManagement,
    ) {
        parent::__construct($context, $requestedShipmentResolver);
    }

    public function execute(): Redirect
    {
        $shipment = $this->getShipment();
        if ($shipment !== null) {
            try {
                $shipment = $this->shipmentManagement->cancel((int)$shipment->getId());
                $this->messageManager->addSuccessMessage(
                    __('Polkurier order %1 cancelled.', $shipment->getPolkurierNumber()),
                );
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->redirectBack();
    }
}
