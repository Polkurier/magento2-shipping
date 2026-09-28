<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractInlineEdit;
use Polkurier\Shipping\Model\Admin\AddressHandler;

class InlineEdit extends AbstractInlineEdit
{
    public function __construct(Context $context, AddressHandler $handler, JsonFactory $jsonFactory)
    {
        parent::__construct($context, $handler, $jsonFactory);
    }

    protected function getEditableFields(): array
    {
        return ['name', 'person', 'company', 'street', 'house_number', 'postcode', 'city', 'phone', 'email', 'is_default', 'is_cover'];
    }
}
