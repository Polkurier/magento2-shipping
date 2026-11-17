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
use Polkurier\Shipping\Model\RequestParamReader;

/**
 * Links a shipment whose outcome is unknown to the Polkurier order the merchant names.
 */
class Adopt extends AbstractShipmentAction implements HttpPostActionInterface
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
        $number = \trim(RequestParamReader::getString($this->getRequest(), 'polkurier_number'));
        if ($shipment !== null && $number !== '') {
            try {
                $shipment = $this->shipmentManagement->adopt((int)$shipment->getId(), $number);
                $this->messageManager->addSuccessMessage(
                    __('Polkurier order %1 linked.', $shipment->getPolkurierNumber()),
                );
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->redirectBack();
    }
}
