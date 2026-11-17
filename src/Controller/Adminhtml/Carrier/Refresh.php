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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Controller\Adminhtml\ReturnsBackTrait;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigSynchronizer;
use Polkurier\Shipping\Model\Config\ConfigProvider;

/**
 * Reads the carriers the Polkurier account offers.
 */
class Refresh extends Action implements HttpPostActionInterface
{
    use ReturnsBackTrait;

    public const string ADMIN_RESOURCE = 'Magento_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly CarrierConfigSynchronizer $carrierConfigSynchronizer,
        private readonly ConfigProvider $configProvider,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        if (!$this->configProvider->hasCredentials()) {
            $this->messageManager->addErrorMessage(ConfigProvider::describeMissingCredentials());

            return $this->returnBack('*/*/index');
        }
        try {
            $count = $this->carrierConfigSynchronizer->synchronize();
            $this->messageManager->addSuccessMessage(__('The account offers %1 carriers.', $count));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->returnBack('*/*/index');
    }
}
