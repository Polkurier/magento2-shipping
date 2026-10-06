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
use Polkurier\Shipping\Model\MoneyBox\MoneyBoxOrders;
use Polkurier\Shipping\Model\MoneyBox\OrderNumbers;
use Polkurier\Shipping\Model\MoneyBox\Payouts;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;

/**
 * What a payout as filled in would cost. Moves nothing.
 */
class Quote extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::money_box_payout';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly Payouts $payouts,
        private readonly MoneyBoxOrders $moneyBoxOrders,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $numbers = OrderNumbers::parse((string)($data['orders'] ?? ''));
        $fee = $this->payouts->quote($numbers, MoneyBoxPayoutTypeEnum::tryFrom((string)($data['payout_type'] ?? '')));

        return [
            'available' => $this->payouts->getAvailableBalance(),
            // Polkurier leaves out what it can't pay out without saying so.
            'unknown' => $this->moneyBoxOrders->unknown($numbers),
            'fee' => $fee->getFee(),
            'fee_net' => $fee->getFeeNett(),
            'free_left' => $fee->getFreePayoutsLeft(),
            'free_total' => $fee->getFreePayoutsTotal(),
            'message' => (string)\__(
                'Fee: %1 PLN gross (%2 PLN net). Free payouts left: %3 of %4.',
                \number_format($fee->getFee(), 2),
                \number_format($fee->getFeeNett(), 2),
                $fee->getFreePayoutsLeft(),
                $fee->getFreePayoutsTotal(),
            ),
        ];
    }
}
