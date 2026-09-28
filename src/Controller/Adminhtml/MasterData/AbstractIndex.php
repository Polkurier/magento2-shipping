<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\MasterData;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\Model\View\Result\Page;

abstract class AbstractIndex extends AbstractAction implements HttpGetActionInterface
{
    public function execute(): Page
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if (!$page instanceof Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu($this->handler->getAclResource());
        $page->getConfig()->getTitle()->prepend($this->handler->getLabel());

        return $page;
    }
}
