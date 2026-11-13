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
use Polkurier\Shipping\Model\Payout\MoneyBoxOrderFinder;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;

/**
 * What the payout, as filled in, would cost. Moves no money.
 */
class Quote extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::payout_request';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly PayoutManager $payoutManager,
        private readonly MoneyBoxOrderFinder $moneyBoxOrderFinder,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $numbers = PayoutManager::parseOrderNumbers((string)($data['orders'] ?? ''));
        $fee = $this->payoutManager->quote(
            $numbers,
            MoneyBoxPayoutTypeEnum::tryFrom((string)($data['payout_type'] ?? '')),
        );

        return [
            'available' => $this->payoutManager->getAvailableBalance(),
            // Polkurier silently leaves out what it can't pay out.
            'unknown' => $this->moneyBoxOrderFinder->findUnknown($numbers),
            'fee' => $fee->fee,
            'fee_net' => $fee->feeNet,
            'free_left' => $fee->freePayoutsLeft,
            'free_total' => $fee->freePayoutsTotal,
            'message' => (string)__(
                'Fee: %1 PLN gross (%2 PLN net). Free payouts left: %3 of %4.',
                \number_format($fee->fee, 2),
                \number_format($fee->feeNet, 2),
                $fee->freePayoutsLeft,
                $fee->freePayoutsTotal,
            ),
        ];
    }
}
