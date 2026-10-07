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
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Admin\FlashMessageCollector;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;

/**
 * Reads the current state of the selected placed Polkurier orders.
 */
class MassRefresh extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        RequestedShipments $requestedShipments,
        private readonly ShipmentManagementInterface $management,
        private readonly FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $requestedShipments);
    }

    public function execute(): Redirect
    {
        $refreshed = 0;
        $failed = [];
        $flash = $this->flashMessageCollectorFactory->create();
        try {
            foreach ($this->getSelectedShipments() as $shipment) {
                if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
                    continue;
                }
                try {
                    $this->management->refreshStatus((int)$shipment->getId());
                    $refreshed++;
                } catch (LocalizedException $e) {
                    $failed[] = $shipment->getPolkurierNumber() . ': ' . $e->getMessage();
                }
            }
        } catch (LocalizedException $e) {
            $flash->error($e->getMessage());
        }
        $flash->success(__('%1 shipment(s) updated.', $refreshed));
        if ($failed !== []) {
            $notUpdated = __('Not updated: %1', \implode('; ', $failed));
            $refreshed > 0 ? $flash->warning($notUpdated) : $flash->error($notUpdated);
        }
        $flash->send($this->messageManager);

        return $this->back();
    }
}
