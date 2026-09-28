<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\MasterData;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Admin\SyncableEntityHandler;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\WebsiteScope;

/**
 * Synchronizes the entity with Polkurier on every website that has credentials.
 */
abstract class AbstractSync extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        SyncableEntityHandler $handler,
        private readonly WebsiteScope $websiteScope,
        private readonly Config $config,
    ) {
        parent::__construct($context, $handler);
    }

    public function execute(): Redirect
    {
        foreach ($this->websiteScope->getWebsiteIds() as $websiteId) {
            if (!$this->config->hasCredentials($this->websiteScope->getStoreId($websiteId))) {
                continue;
            }
            try {
                $this->reportSync($this->handler->synchronize($websiteId));
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
