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
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Reads the carriers each website's Polkurier account offers.
 */
class Refresh extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly CarrierConfigSynchronizer $synchronizer,
        private readonly WebsiteScope $websiteScope,
        private readonly Config $config,
        private readonly ErrorMessages $errorMessages,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $any = false;
        foreach ($this->websiteScope->getWebsiteIds() as $websiteId) {
            if (!$this->config->hasCredentials($this->websiteScope->getStoreId($websiteId))) {
                continue;
            }
            $any = true;
            try {
                $count = $this->synchronizer->synchronize($websiteId);
                $this->messageManager->addSuccessMessage(\__('Website %1: the account offers %2 carriers.', $websiteId, $count));
            } catch (PolkurierException $e) {
                $this->messageManager->addErrorMessage(\__('Website %1: %2', $websiteId, $this->errorMessages->describe($e)));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }
        if (!$any) {
            $this->messageManager->addErrorMessage(\__('Configure the Polkurier customer ID and API token first.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
