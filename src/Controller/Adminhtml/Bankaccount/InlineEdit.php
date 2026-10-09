<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractInlineEdit;
use Polkurier\Shipping\Model\BankAccount\BankAccountHandler;

/**
 * Saves the bank accounts edited in place in their grid.
 */
class InlineEdit extends AbstractInlineEdit
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function __construct(
        Context $context,
        BankAccountHandler $bankAccountHandler,
        JsonFactory $jsonFactory,
    ) {
        parent::__construct($context, $bankAccountHandler, $jsonFactory);
    }

    protected function getEditableFields(): array
    {
        return [
            BankAccountInterface::FIELD_NAME,
            BankAccountInterface::FIELD_NUMBER,
            BankAccountInterface::FIELD_IS_DEFAULT,
        ];
    }
}
