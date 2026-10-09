<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * The form of a new or existing entry of a list synchronized with Polkurier.
 */
abstract class AbstractEdit extends AbstractAction implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('id');
        if ($id) {
            try {
                $this->entityHandler->get($id);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());

                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(static::ADMIN_RESOURCE);
        $page->getConfig()->getTitle()->prepend($this->entityHandler->getLabel());
        $page->getConfig()->getTitle()->prepend($id ? (string)__('Edit') : (string)__('New entry'));

        return $page;
    }
}
