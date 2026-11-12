<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
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
        EntityHandlerInterface $entityHandler,
        private readonly ConfigProvider $configProvider,
        private readonly FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $entityHandler);
    }

    public function execute(): Redirect
    {
        if (!$this->configProvider->hasCredentials()) {
            $this->messageManager->addErrorMessage(ConfigProvider::describeMissingCredentials());

            return $this->returnBack('*/*/index');
        }
        try {
            $this->reportSync($this->entityHandler->synchronize());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->returnBack('*/*/index');
    }

    /**
     * Reports a full synchronization as one message, with one line per kind.
     *
     * @param string[] $lines
     */
    private function reportSync(array $lines): void
    {
        $flash = $this->flashMessageCollectorFactory->create();
        foreach ($lines as $line) {
            $flash->addNotice($line);
        }
        $flash->send($this->messageManager);
    }
}
