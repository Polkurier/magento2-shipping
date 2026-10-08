<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\AbstractDelete;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateHandler;

/**
 * Deletes a pack template, here and, with the next synchronization, in Polkurier.
 */
class Delete extends AbstractDelete
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    public function __construct(
        Context $context,
        PackTemplateHandler $packTemplateHandler,
    ) {
        parent::__construct($context, $packTemplateHandler);
    }
}
