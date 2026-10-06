<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractEdit;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;

/**
 * The form of a new or existing pack template.
 */
class Edit extends AbstractEdit
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    public function __construct(Context $context, PackTemplateHandler $handler)
    {
        parent::__construct($context, $handler);
    }
}
