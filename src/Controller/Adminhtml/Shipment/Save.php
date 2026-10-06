<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Controller\Adminhtml\ReadsPostData;
use Polkurier\Shipping\Model\Admin\Flash;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\RejectedDraft;

/**
 * Places the Polkurier order from the shipment form.
 */
class Save extends Action implements HttpPostActionInterface
{
    use ReadsPostData;

    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly ShipmentManagementInterface $management,
        private readonly DraftHydrator $hydrator,
        private readonly RejectedDraft $rejectedDraft,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $data = $this->getPostData();
        $orderId = (int)($data['order_id'] ?? 0);
        $redirect = $this->resultRedirectFactory->create();

        try {
            $shipment = $this->management->create($orderId, $this->hydrator->fromArray($data));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->rejectedDraft->keep($data);

            return $redirect->setPath('*/*/create', ['order_id' => $orderId]);
        }

        if ($shipment->getState() === ShipmentInterface::STATE_UNKNOWN) {
            $this->messageManager->addWarningMessage(\__(
                'Polkurier did not confirm the order, so it may or may not have been placed. It will not be sent again until it is reconciled: use "Reconcile" in the Polkurier section of the order. (%1)',
                (string)$shipment->getLastError(),
            ));
        } else {
            // Placed, with what went wrong after, if anything (the Magento shipment, say), in one message.
            (new Flash())
                ->success(\__(
                    'Polkurier order %1 placed. Waybills: %2.',
                    $shipment->getPolkurierNumber(),
                    \implode(', ', $shipment->getWaybills()) ?: (string)\__('not issued yet'),
                ))
                ->warning((string)$shipment->getLastError())
                ->send($this->messageManager);
        }

        // To what was placed, whatever came of it.
        return $redirect->setPath('*/*/view', ['id' => $shipment->getId()]);
    }
}
