<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\MasterData;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;

abstract class AbstractDelete extends AbstractAction implements HttpPostActionInterface
{
    public function execute(): Redirect
    {
        try {
            $this->reportSync($this->handler->delete($this->handler->get((int)$this->getRequest()->getParam('id'))));
            $this->messageManager->addSuccessMessage(\__('Deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
