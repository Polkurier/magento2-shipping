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
use Polkurier\Shipping\Model\Shipment\Pickups;
use Polkurier\Shipping\Model\Shipment\RequestedShipments;

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
        private readonly Pickups $pickups,
    ) {
        parent::__construct($context, $requestedShipments);
    }

    public function execute(): Redirect
    {
        $date = (string)$this->getRequest()->getParam('pickup_date');
        $from = (string)$this->getRequest()->getParam('pickup_time_from');
        $to = (string)$this->getRequest()->getParam('pickup_time_to');
        try {
            if (!\preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !\preg_match('/^\d{2}:\d{2}$/', $from) || !\preg_match('/^\d{2}:\d{2}$/', $to)) {
                throw new LocalizedException(\__('Give the collection date as YYYY-MM-DD and the window as HH:MM.'));
            }
            $result = $this->pickups->bookCollective($this->getSelectedShipments(), $date, $from, $to);
            if ($result['booked'] !== []) {
                $this->messageManager->addSuccessMessage(\__('Collection booked for: %1.', \implode(', ', $result['booked'])));
            }
            foreach ($result['failed'] as $number => $reason) {
                $this->messageManager->addErrorMessage(\__('%1: %2', $number, $reason));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
