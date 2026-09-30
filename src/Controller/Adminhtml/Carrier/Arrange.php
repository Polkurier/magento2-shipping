<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Carrier;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Carrier\CarrierOrder;

/**
 * Saves the order of the carriers' rows after one was dragged in the carriers grid.
 */
class Arrange extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CarrierOrder $carrierOrder,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $ids = \array_values(\array_filter(\array_map('intval', (array)$this->getRequest()->getParam('ids', []))));
        try {
            $this->carrierOrder->arrange($ids);
        } catch (LocalizedException $e) {
            return $this->jsonFactory->create()->setData(['error' => true, 'message' => $e->getMessage()]);
        }

        return $this->jsonFactory->create()->setData(['error' => false]);
    }
}
