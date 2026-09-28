<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractSave;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;

class Save extends AbstractSave
{
    public function __construct(Context $context, PackTemplateHandler $handler, DataPersistorInterface $dataPersistor)
    {
        parent::__construct($context, $handler, $dataPersistor);
    }
}
