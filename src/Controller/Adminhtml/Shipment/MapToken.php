<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * A fresh token for the pickup point map, for a shipment screen or label window whose token is
 * gone or about to run out.
 */
class MapToken extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly MapTokenProvider $tokenProvider,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        try {
            $token = $this->tokenProvider->getToken();
            $expires = $this->tokenProvider->getExpiry();
        } catch (PolkurierException) {
            $token = '';
            $expires = 0;
        }

        return $this->jsonFactory->create()->setData(['token' => $token, 'expires' => $expires]);
    }
}
