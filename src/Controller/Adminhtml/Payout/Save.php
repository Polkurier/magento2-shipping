<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Payout;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Controller\Adminhtml\ReadsPostDataTrait;
use Polkurier\Shipping\Enum\Adminhtml\FormMemoryEnum;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxFeePaymentTypeEnum;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;

/**
 * Requests a payout from the money box. Moves money; the code and PIN are passed on, not kept.
 */
class Save extends Action implements HttpPostActionInterface
{
    use ReadsPostDataTrait;

    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::payout_request';
    public const string ROUTE = 'polkurier/payout/save';

    private const array KEPT_FIELDS = [
        'orders',
        'payout_type',
        'fee_payment_type',
        'bank_account_choice',
        'bank_account_number',
    ];

    public function __construct(
        Context $context,
        private readonly PayoutManager $payoutManager,
        private readonly DataPersistorInterface $dataPersistor,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $data = $this->getPostData();
        try {
            $payoutType = MoneyBoxPayoutTypeEnum::tryFrom((string)($data['payout_type'] ?? ''))
                ?? MoneyBoxPayoutTypeEnum::Account;
            $payout = $this->payoutManager->createPayout(
                PayoutManager::parseOrderNumbers((string)($data['orders'] ?? '')),
                $payoutType,
                MoneyBoxFeePaymentTypeEnum::tryFrom((string)($data['fee_payment_type'] ?? ''))
                    ?? MoneyBoxFeePaymentTypeEnum::MoneyBox,
                (string)($data['two_factor_token'] ?? ''),
                (string)($data['two_factor_code'] ?? ''),
                isset($data['security_pin']) ? (string)$data['security_pin'] : null,
                $payoutType === MoneyBoxPayoutTypeEnum::Account
                    ? PayoutManager::resolveChosenAccount(
                        (string)($data['bank_account_choice'] ?? PayoutManager::ANOTHER_ACCOUNT),
                        (string)($data['bank_account_number'] ?? ''),
                    )
                    : null,
            );
            $this->messageManager->addSuccessMessage(__(
                'Payout %1 requested: %2 PLN.',
                $payout->number,
                \number_format($payout->amount, 2),
            ));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            // Back to the form with what was chosen; the code, its token and the PIN are asked again.
            $this->dataPersistor->set(
                FormMemoryEnum::Payout->value,
                \array_intersect_key($data, \array_flip(self::KEPT_FIELDS)),
            );

            return $this->resultRedirectFactory->create()->setPath('*/*/request');
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
