<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Controller\Adminhtml\Carrier\Save as CarrierSave;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;

/**
 * A fresh token for the pickup point map, for a shipment screen, label window or carrier page whose
 * token is missing or about to run out.
 */
class MapToken extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Magento_Sales::ship';
    public const string ROUTE = 'polkurier/shipment/mapToken';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly MapTokenProvider $mapTokenProvider,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        return $this->jsonFactory->create()->setData($this->mapTokenProvider->getTokenWithExpiry());
    }

    /**
     * Also for choosing a carrier's default drop-off point.
     */
    protected function _isAllowed(): bool
    {
        return parent::_isAllowed() || $this->_authorization->isAllowed(CarrierSave::ADMIN_RESOURCE);
    }
}
