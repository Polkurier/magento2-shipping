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
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\Shipment\Details\DetailsLoader;

/**
 * Reads the current state of one Polkurier order, with the carrier's scans, and keeps the local
 * copy of both.
 */
class Refresh extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        RequestedShipmentResolver $requestedShipmentResolver,
        private readonly ShipmentManagementInterface $shipmentManagement,
        private readonly DetailsLoader $detailsLoader,
        private readonly FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $requestedShipmentResolver);
    }

    public function execute(): Redirect
    {
        $shipment = $this->getShipment();
        if ($shipment !== null) {
            try {
                $shipment = $this->shipmentManagement->refreshStatus((int)$shipment->getId());
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());

                return $this->redirectBack();
            }
            $flash = $this->flashMessageCollectorFactory->create()->addSuccess(
                __('Polkurier order %1: %2.', $shipment->getPolkurierNumber(), (string)$shipment->getStatusLabel()),
            );
            try {
                $this->detailsLoader->refreshTracking($shipment);
            } catch (LocalizedException $e) {
                $flash->addWarning($e->getMessage());
            }
            $flash->send($this->messageManager);
        }

        return $this->redirectBack();
    }
}
