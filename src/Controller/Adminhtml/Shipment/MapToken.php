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
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;

/**
 * A fresh token for the pickup point map: for a shipment screen or label window whose token is
 * gone or about to run out, and for the address form's drop-off point, which asks for one when the
 * map opens.
 */
class MapToken extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly MapTokenProvider $mapTokenProvider,
    ) {
        parent::__construct($context);
    }

    /**
     * Also for choosing an address's drop-off point.
     */
    protected function _isAllowed(): bool
    {
        return parent::_isAllowed() || $this->_authorization->isAllowed('Polkurier_Shipping::addresses');
    }

    public function execute(): Json
    {
        return $this->jsonFactory->create()->setData($this->mapTokenProvider->getTokenWithExpiry());
    }
}
