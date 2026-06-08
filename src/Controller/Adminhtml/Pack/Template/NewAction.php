<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Pack\Template;

use Magento\Backend\Model\View\Result\Forward;
use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;

class NewAction extends BaseAction
{
    /**
     * @return Forward
     */
    public function execute(): Forward
    {
        return $this->resultForwardFactory->create()->forward('edit');
    }
}
