<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Url\EncoderInterface;
use Polkurier\Shipping\Model\Shipment\RequestedShipments;

/**
 * Takes the grid selection to the collective pickup form.
 */
class MassPickup extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        RequestedShipments $requestedShipments,
        private readonly EncoderInterface $urlEncoder,
    ) {
        parent::__construct($context, $requestedShipments);
    }

    public function execute(): Redirect
    {
        try {
            $ids = \array_map(static fn($shipment): int => (int)$shipment->getId(), $this->getSelectedShipments());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $ids = [];
        }
        if ($ids === []) {
            return $this->back();
        }

        // The form takes along where the booking was started from, to go back there once booked.
        $orderId = (int)$this->getRequest()->getParam('order_id');

        return $this->resultRedirectFactory->create()->setPath('*/*/pickupForm', \array_filter([
            'ids' => \implode(',', $ids),
            'order_id' => $orderId ?: null,
            ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlEncoder->encode((string)$this->_redirect->getRefererUrl()),
        ]));
    }
}
