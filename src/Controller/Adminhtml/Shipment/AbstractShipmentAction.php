<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Controller\Adminhtml\ReturnsBackTrait;

/**
 * Shared plumbing of the actions on existing Polkurier shipments: reaching the shipments a request
 * names, one by id or many through a grid selection, and going back to where it came from.
 */
abstract class AbstractShipmentAction extends Action
{
    use ReturnsBackTrait;

    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        private readonly RequestedShipments $requestedShipments,
    ) {
        parent::__construct($context);
    }

    protected function getShipment(): ?ShipmentInterface
    {
        try {
            return $this->requestedShipments->getOne($this->getRequest());
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return null;
        }
    }

    /**
     * The shipments named by ids, selected in a grid, or the one named by id.
     *
     * @return ShipmentInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getSelectedShipments(): array
    {
        return $this->requestedShipments->getSelected($this->getRequest());
    }

    /**
     * Back to where the action was started; without that, to the order the request names, or
     * the shipments grid.
     *
     * @param string[] $gone paths of pages the action did away with
     */
    protected function redirectBack(array $gone = []): Redirect
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');

        return $orderId
            ? $this->returnBack('sales/order/view', ['order_id' => $orderId], $gone)
            : $this->returnBack('polkurier/shipment/index', [], $gone);
    }
}
