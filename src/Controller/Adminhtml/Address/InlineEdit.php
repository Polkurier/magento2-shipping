<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractInlineEdit;
use Polkurier\Shipping\Model\Address\AddressHandler;

/**
 * Saves the sender addresses edited in place in their grid.
 */
class InlineEdit extends AbstractInlineEdit
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::addresses';

    public function __construct(
        Context $context,
        AddressHandler $addressHandler,
        JsonFactory $jsonFactory,
    ) {
        parent::__construct($context, $addressHandler, $jsonFactory);
    }

    protected function getEditableFields(): array
    {
        return [
            AddressInterface::FIELD_NAME,
            AddressInterface::FIELD_PERSON,
            AddressInterface::FIELD_COMPANY,
            AddressInterface::FIELD_STREET,
            AddressInterface::FIELD_HOUSE_NUMBER,
            AddressInterface::FIELD_POSTCODE,
            AddressInterface::FIELD_CITY,
            AddressInterface::FIELD_PHONE,
            AddressInterface::FIELD_EMAIL,
            AddressInterface::FIELD_IS_DEFAULT,
            AddressInterface::FIELD_IS_COVER,
        ];
    }
}
