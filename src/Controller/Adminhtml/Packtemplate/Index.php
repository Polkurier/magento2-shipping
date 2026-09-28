<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;

use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractIndex;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;

class Index extends AbstractIndex
{
    public function __construct(Context $context, PackTemplateHandler $handler)
    {
        parent::__construct($context, $handler);
    }
}
