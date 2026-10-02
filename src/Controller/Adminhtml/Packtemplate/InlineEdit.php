<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractInlineEdit;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;

class InlineEdit extends AbstractInlineEdit
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    public function __construct(Context $context, PackTemplateHandler $handler, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $handler, $jsonFactory);
    }

    protected function getEditableFields(): array
    {
        return ['name', 'pack_type', 'length', 'width', 'height', 'weight', 'is_default'];
    }
}
