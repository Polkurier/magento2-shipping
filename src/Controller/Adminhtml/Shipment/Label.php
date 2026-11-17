<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\RequestParamReader;
use Polkurier\Shipping\Model\Shipment\DocumentService;
use Polkurier\Webservice\Enum\Order\LabelFileFormatEnum;

/**
 * Downloads the labels of one shipment (by id) or of the shipments selected in a grid, as one file:
 * a PDF, or in the label printer language given as "format" (a {@see LabelFileFormatEnum} value).
 */
class Label extends AbstractShipmentAction implements HttpGetActionInterface, HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        RequestedShipmentResolver $requestedShipmentResolver,
        private readonly DocumentService $documentService,
        private readonly FileFactory $fileFactory,
    ) {
        parent::__construct($context, $requestedShipmentResolver);
    }

    public function execute(): ResponseInterface|ResultInterface
    {
        try {
            $document = $this->documentService->getLabels(
                $this->getSelectedShipments(),
                LabelFileFormatEnum::tryFrom(RequestParamReader::getString($this->getRequest(), 'format')),
            );
            // These show on the next page, since this request answers with the file.
            foreach ($document['left_out'] as $message) {
                $this->messageManager->addWarningMessage($message);
            }

            return $this->fileFactory->create(
                $document['file_name'],
                ['type' => 'string', 'value' => $document['content'], 'rm' => false],
                DirectoryList::VAR_DIR,
                $document['content_type'],
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->redirectBack();
    }
}
