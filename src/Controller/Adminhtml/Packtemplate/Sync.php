<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\AbstractSync;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateHandler;

/**
 * Synchronizes the pack templates with the Polkurier account now.
 */
class Sync extends AbstractSync
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    public function __construct(
        Context $context,
        PackTemplateHandler $packTemplateHandler,
        ConfigProvider $config,
        FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $packTemplateHandler, $config, $flashMessageCollectorFactory);
    }
}
