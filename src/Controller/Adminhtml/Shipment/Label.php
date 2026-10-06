<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Shipment\DocumentDownload;
use Polkurier\Shipping\Model\Shipment\Documents;
use Polkurier\Shipping\Model\Shipment\RequestedShipments;

/**
 * Downloads the labels of one shipment (by id) or of the shipments selected in a grid, as one file.
 */
class Label extends AbstractShipmentAction implements HttpGetActionInterface, HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        RequestedShipments $requestedShipments,
        private readonly Documents $documents,
        private readonly DocumentDownload $documentDownload,
    ) {
        parent::__construct($context, $requestedShipments);
    }

    public function execute(): ResponseInterface|ResultInterface
    {
        try {
            $document = $this->documents->getLabels($this->getSelectedShipments());
            // Seen on the next page, as the file is the answer to this one.
            foreach ($document['left_out'] as $message) {
                $this->messageManager->addWarningMessage($message);
            }

            return $this->documentDownload->send($document);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->back();
    }
}
