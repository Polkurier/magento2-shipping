<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Carrier;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Carrier\CarrierLogoStorage;
use Polkurier\Shipping\Model\RequestParamReader;

/**
 * Receives a logo from the carrier form's image uploader. The carrier is only changed when the
 * form is saved.
 */
class UploadLogo extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Magento_Shipping::carriers';
    public const string ROUTE = 'polkurier/carrier/uploadLogo';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CarrierLogoStorage $carrierLogoStorage,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            return $result->setData(
                $this->carrierLogoStorage->upload(RequestParamReader::getString(
                    $this->getRequest(),
                    'param_name',
                    CarrierConfigInterface::FIELD_LOGO,
                )),
            );
        } catch (LocalizedException $e) {
            return $result->setData(['error' => $e->getMessage(), 'errorcode' => 1]);
        }
    }
}
