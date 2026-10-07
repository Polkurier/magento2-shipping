<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\ShipmentManagementInterface;

/**
 * Looks for a Polkurier order whose creation went unanswered.
 */
class Reconcile extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        RequestedShipments $requestedShipments,
        private readonly ShipmentManagementInterface $management,
    ) {
        parent::__construct($context, $requestedShipments);
    }

    public function execute(): Redirect
    {
        $shipment = $this->getShipment();
        if ($shipment !== null) {
            try {
                $shipment = $this->management->reconcile((int)$shipment->getId());
                $shipment->getState() === \Polkurier\Shipping\Api\Data\ShipmentInterface::STATE_CREATED
                    ? $this->messageManager->addSuccessMessage(
                        __('Polkurier order %1 found and linked.', $shipment->getPolkurierNumber()),
                    )
                    : $this->messageManager->addNoticeMessage((string)$shipment->getLastError());
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->back();
    }
}
