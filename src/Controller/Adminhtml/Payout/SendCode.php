<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Payout;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Admin\FlashMessageCollector;
use Polkurier\Shipping\Model\Payout\PayoutManager;

/**
 * Has Polkurier send the account holder the confirmation code a payout needs.
 */
class SendCode extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::payout_request';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly PayoutManager $payoutManager,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $token = $this->payoutManager->requestCode();

        return ['token' => $token->id, 'message' => FlashMessageCollector::formatCodeSent($token)];
    }
}
