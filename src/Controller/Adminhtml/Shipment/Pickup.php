<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\RequestParam;
use Polkurier\Shipping\Model\Shipment\PickupService;

/**
 * Books one courier collection for the selected shipments that await a collective pickup.
 * The date and window come with the grid's mass action.
 */
class Pickup extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        RequestedShipments $requestedShipments,
        private readonly PickupService $pickupService,
        private readonly FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $requestedShipments);
    }

    public function execute(): Redirect
    {
        $date = RequestParam::getString($this->getRequest(), 'pickup_date');
        $from = RequestParam::getString($this->getRequest(), 'pickup_time_from');
        $to = RequestParam::getString($this->getRequest(), 'pickup_time_to');
        try {
            PickupService::buildWindow($date, $from, $to);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            // Back to the form, with the shipments chosen.
            return $this->resultRedirectFactory->create()->setPath('*/*/pickupForm', \array_filter([
                'ids' => RequestParam::getString($this->getRequest(), 'ids') ?: null,
                'order_id' => (int)$this->getRequest()->getParam('order_id') ?: null,
                ActionInterface::PARAM_NAME_URL_ENCODED
                    => RequestParam::getString($this->getRequest(), ActionInterface::PARAM_NAME_URL_ENCODED) ?: null,
            ]));
        }
        $flash = $this->flashMessageCollectorFactory->create();
        try {
            $result = $this->pickupService->bookCollective($this->getSelectedShipments(), $date, $from, $to);
            if ($result['booked'] !== []) {
                $flash->addSuccess(__('Collective pickup booked for: %1.', \implode(', ', $result['booked'])));
            }
            if ($result['failed'] !== []) {
                $reasons = [];
                foreach ($result['failed'] as $number => $reason) {
                    $reasons[] = $number . ': ' . $reason;
                }
                $notBooked = __('Not booked for %1', \implode('; ', $reasons));
                $result['booked'] !== [] ? $flash->addWarning($notBooked) : $flash->addError($notBooked);
            }
        } catch (LocalizedException $e) {
            $flash->addError($e->getMessage());
        }
        $flash->send($this->messageManager);

        // Where the booking was started from, as the form took it along.
        return $this->redirectBack();
    }
}
