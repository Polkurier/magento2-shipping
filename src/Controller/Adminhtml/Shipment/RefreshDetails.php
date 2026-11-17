<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Shipment\Details\DetailsLoader;
use Polkurier\Shipping\ViewModel\Adminhtml\ShipmentDetails;

/**
 * Called by the shipment details page once it shows what is stored: reads from Polkurier the order
 * copy that's missing and the scans that may have moved on, and answers with the page's sections
 * built again ("view"), the shipment's state, and "errors". When nothing could be read, it answers
 * with the errors only.
 */
class RefreshDetails extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Magento_Sales::shipment';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly DetailsLoader $detailsLoader,
        private readonly ShipmentDetails $shipmentDetails,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $shipment = $this->shipmentRepository->get((int)$this->getRequest()->getParam('id'));
        ['shipment' => $refreshed, 'errors' => $errors] = $this->detailsLoader->refresh($shipment);
        if ($refreshed === null) {
            return ['error' => true, 'message' => \implode(' ', $errors), 'errors' => $errors];
        }

        return [
            'view' => $this->shipmentDetails->build($refreshed, $errors),
            'state' => $refreshed->getState(),
            'errors' => $errors,
        ];
    }

    protected function buildRefusal(LocalizedException $e): array
    {
        return ['errors' => [$e->getMessage()]];
    }
}
