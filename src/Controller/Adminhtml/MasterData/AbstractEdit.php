<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\MasterData;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Backend\Model\View\Result\Page;

abstract class AbstractEdit extends AbstractAction implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('id');
        if ($id) {
            try {
                $this->handler->get($id);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());

                return $this->resultRedirectFactory->create()->setPath('*/*/index');
            }
        }
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if (!$page instanceof Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu($this->handler->getAclResource());
        $page->getConfig()->getTitle()->prepend($this->handler->getLabel());
        $page->getConfig()->getTitle()->prepend($id ? (string)\__('Edit') : (string)\__('New'));

        return $page;
    }
}
