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
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;

/**
 * Discards a shipment that wasn't placed, rather than sending it again.
 */
class Discard extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        RequestedShipments $requestedShipments,
        private readonly FailedShipmentManager $failedShipmentManager,
    ) {
        parent::__construct($context, $requestedShipments);
    }

    public function execute(): Redirect
    {
        $shipment = $this->getShipment();
        if ($shipment === null) {
            return $this->back();
        }
        try {
            $this->failedShipmentManager->discard((int)$shipment->getId());
            $this->messageManager->addSuccessMessage(__('The shipment that wasn\'t placed was discarded.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        // Back where it was discarded from, unless that was its own page, which is gone with it:
        // then to its order.
        return $this->returnBack(
            'sales/order/view',
            ['order_id' => $shipment->getOrderId()],
            ['polkurier/shipment/view/id/' . (int)$shipment->getId() . '/'],
        );
    }
}
