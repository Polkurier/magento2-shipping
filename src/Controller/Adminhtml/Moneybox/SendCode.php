<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Moneybox;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Admin\CodeSent;
use Polkurier\Shipping\Model\MoneyBox\Payouts;

/**
 * Has Polkurier send the account holder the confirmation code a payout needs.
 */
class SendCode extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::money_box_payout';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Payouts $payouts,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        try {
            $token = $this->payouts->requestCode();
        } catch (LocalizedException $e) {
            return $this->jsonFactory->create()->setData(['error' => true, 'message' => $e->getMessage()]);
        }

        return $this->jsonFactory->create()->setData([
            'error' => false,
            'token' => $token->getId(),
            'message' => CodeSent::message($token),
        ]);
    }
}
