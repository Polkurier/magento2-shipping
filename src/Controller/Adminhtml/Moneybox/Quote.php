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
use Polkurier\Shipping\Model\MoneyBox\OrderNumbers;
use Polkurier\Shipping\Model\MoneyBox\Payouts;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;

/**
 * What a payout as filled in would cost. Moves nothing.
 */
class Quote extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::money_box_payout';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Payouts $payouts,
        private readonly WebsiteScope $websiteScope,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        try {
            $fee = $this->payouts->quote(
                $this->websiteScope->getStoreId((int)($data['website_id'] ?? 0)),
                OrderNumbers::parse((string)($data['orders'] ?? '')),
                MoneyBoxPayoutTypeEnum::tryFrom((string)($data['payout_type'] ?? '')),
            );
        } catch (LocalizedException $e) {
            return $this->jsonFactory->create()->setData(['error' => true, 'message' => $e->getMessage()]);
        }

        return $this->jsonFactory->create()->setData([
            'error' => false,
            'message' => (string)\__(
                'Fee: %1 PLN gross (%2 PLN net). Free payouts left: %3 of %4.',
                \number_format($fee->getFee(), 2),
                \number_format($fee->getFeeNett(), 2),
                $fee->getFreePayoutsLeft(),
                $fee->getFreePayoutsTotal(),
            ),
        ]);
    }
}
