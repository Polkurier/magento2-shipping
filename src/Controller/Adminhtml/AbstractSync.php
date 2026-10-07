<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;

/**
 * Synchronizes the entity with Polkurier, once the credentials are set.
 */
abstract class AbstractSync extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        EntityHandlerInterface $handler,
        private readonly ConfigProvider $configProvider,
        FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $handler, $flashMessageCollectorFactory);
    }

    public function execute(): Redirect
    {
        if (!$this->configProvider->hasCredentials()) {
            $this->messageManager->addErrorMessage(ConfigProvider::credentialsMissing());

            return $this->returnBack('*/*/index');
        }
        try {
            $this->reportSync($this->handler->synchronize());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->returnBack('*/*/index');
    }
}
