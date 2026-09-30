<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractSync;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;

class Sync extends AbstractSync
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    public function __construct(Context $context, PackTemplateHandler $handler, Config $config)
    {
        parent::__construct($context, $handler, $config);
    }
}
