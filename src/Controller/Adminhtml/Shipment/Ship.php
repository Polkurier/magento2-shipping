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
 * Creates the Magento shipment of a placed Polkurier order when creating it failed at placing.
 */
class Ship extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Magento_Sales::ship';

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
                $shipment = $this->shipmentManagement->shipOrder((int)$shipment->getId());
                $this->messageManager->addSuccessMessage(
                    __('The order is shipped with Polkurier order %1.', (string)$shipment->getPolkurierNumber()),
                );
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->redirectBack();
    }
}
