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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Carrier\CarrierConfigSynchronizer;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Reads the carriers the Polkurier account offers.
 */
class Refresh extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly CarrierConfigSynchronizer $synchronizer,
        private readonly Config $config,
        private readonly ErrorMessages $errorMessages,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        if (!$this->config->hasCredentials()) {
            $this->messageManager->addErrorMessage(\__('Configure the Polkurier customer ID and API token first.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
        try {
            $count = $this->synchronizer->synchronize();
            $this->messageManager->addSuccessMessage(\__('The account offers %1 carriers.', $count));
        } catch (PolkurierException $e) {
            $this->messageManager->addErrorMessage($this->errorMessages->describe($e));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
