<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Admin\FlashMessageCollector;
use Polkurier\Shipping\Model\BankAccount\Publisher;

/**
 * Has Polkurier send the two-factor code that confirms saving a bank account.
 */
class SendCode extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly Publisher $publisher,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $token = $this->publisher->requestCode();

        return ['token' => $token->getId(), 'message' => FlashMessageCollector::formatCodeSent($token)];
    }
}
