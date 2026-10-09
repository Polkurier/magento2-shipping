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
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;

/**
 * Reads the current state of the selected placed Polkurier orders.
 */
class MassRefresh extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        RequestedShipments $requestedShipments,
        private readonly StatusRefreshService $statusRefreshService,
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
            $placed = [];
            foreach ($this->getSelectedShipments() as $shipment) {
                if ($shipment->getState() === ShipmentInterface::STATE_CREATED) {
                    $placed[(int)$shipment->getId()] = $shipment;
                }
            }
            foreach ($this->statusRefreshService->refreshMany(\array_keys($placed)) as $shipmentId => $result) {
                if ($result instanceof LocalizedException) {
                    $failed[] = $placed[$shipmentId]->getPolkurierNumber() . ': ' . $result->getMessage();
                } elseif ($result instanceof \Throwable) {
                    throw $result;
                } else {
                    $refreshed++;
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
