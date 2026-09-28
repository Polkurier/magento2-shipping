<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractSync;
use Polkurier\Shipping\Model\Admin\BankAccountHandler;

class Sync extends AbstractSync
{
    public function __construct(Context $context, BankAccountHandler $handler, WebsiteScope $websiteScope, Config $config)
    {
        parent::__construct($context, $handler, $websiteScope, $config);
    }
}
