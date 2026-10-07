<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractInlineEdit;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateHandler;

/**
 * Saves the pack templates edited in place in their grid.
 */
class InlineEdit extends AbstractInlineEdit
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    public function __construct(
        Context $context,
        PackTemplateHandler $handler,
        JsonFactory $jsonFactory,
        FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $handler, $jsonFactory, $flashMessageCollectorFactory);
    }

    protected function getEditableFields(): array
    {
        return [
            PackTemplateInterface::FIELD_NAME,
            PackTemplateInterface::FIELD_PACK_TYPE,
            PackTemplateInterface::FIELD_LENGTH,
            PackTemplateInterface::FIELD_WIDTH,
            PackTemplateInterface::FIELD_HEIGHT,
            PackTemplateInterface::FIELD_WEIGHT,
            PackTemplateInterface::FIELD_TARE,
            PackTemplateInterface::FIELD_IS_DEFAULT,
        ];
    }
}
