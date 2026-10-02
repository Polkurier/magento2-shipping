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
use Polkurier\Shipping\Model\Admin\EntityHandlerInterface;
use Polkurier\Shipping\Model\Config;

/**
 * Synchronizes the entity with Polkurier, once the credentials are set.
 */
abstract class AbstractSync extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        EntityHandlerInterface $handler,
        private readonly Config $config,
    ) {
        parent::__construct($context, $handler);
    }

    public function execute(): Redirect
    {
        if (!$this->config->hasCredentials()) {
            $this->messageManager->addErrorMessage(\__('Configure the Polkurier customer ID and API token first.'));

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
