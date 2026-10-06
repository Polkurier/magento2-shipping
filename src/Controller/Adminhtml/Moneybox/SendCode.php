<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Moneybox;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Admin\CodeSent;
use Polkurier\Shipping\Model\MoneyBox\Payouts;

/**
 * Has Polkurier send the account holder the confirmation code a payout needs.
 */
class SendCode extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::money_box_payout';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly Payouts $payouts,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $token = $this->payouts->requestCode();

        return ['token' => $token->getId(), 'message' => CodeSent::message($token)];
    }
}
