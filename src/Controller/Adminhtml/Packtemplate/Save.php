<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractSave;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateHandler;

/**
 * Saves a pack template from its form.
 */
class Save extends AbstractSave
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    public function __construct(
        Context $context,
        PackTemplateHandler $packTemplateHandler,
        DataPersistorInterface $dataPersistor,
    ) {
        parent::__construct($context, $packTemplateHandler, $dataPersistor);
    }
}
