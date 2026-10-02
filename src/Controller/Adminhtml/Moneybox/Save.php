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
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\MoneyBox\OrderNumbers;
use Polkurier\Shipping\Model\MoneyBox\Payouts;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxFeePaymentTypeEnum;
use Polkurier\Webservice\Enum\MoneyBox\MoneyBoxPayoutTypeEnum;

/**
 * Requests a payout from the money box. Moves money; the code and PIN are passed on, not kept.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::money_box_payout';

    /**
     * Where what was chosen waits for the form, when the payout couldn't be requested.
     */
    public const string PERSISTOR_KEY = 'polkurier_payout';

    private const array KEPT_FIELDS = ['orders', 'payout_type', 'fee_payment_type', 'bank_account_number'];

    public function __construct(
        Context $context,
        private readonly Payouts $payouts,
        private readonly DataPersistorInterface $dataPersistor,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $data = $this->getPostData();
        try {
            if (empty($data['two_factor_token']) || empty($data['two_factor_code'])) {
                throw new LocalizedException(\__('Send yourself the confirmation code and enter it first.'));
            }
            $payout = $this->payouts->createPayout(
                OrderNumbers::parse((string)($data['orders'] ?? '')),
                MoneyBoxPayoutTypeEnum::tryFrom((string)($data['payout_type'] ?? '')) ?? MoneyBoxPayoutTypeEnum::Account,
                MoneyBoxFeePaymentTypeEnum::tryFrom((string)($data['fee_payment_type'] ?? '')) ?? MoneyBoxFeePaymentTypeEnum::MoneyBox,
                (string)$data['two_factor_token'],
                (string)$data['two_factor_code'],
                isset($data['security_pin']) ? (string)$data['security_pin'] : null,
                isset($data['bank_account_number']) ? (string)$data['bank_account_number'] : null,
            );
            $this->messageManager->addSuccessMessage(\__(
                'Payout %1 requested: %2 PLN.',
                $payout->getNumber(),
                \number_format($payout->getAmount(), 2),
            ));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            // Back to the form with what was chosen; the code, its token and the PIN are asked again.
            $this->dataPersistor->set(self::PERSISTOR_KEY, \array_intersect_key($data, \array_flip(self::KEPT_FIELDS)));

            return $this->resultRedirectFactory->create()->setPath('*/*/payout');
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }

    /**
     * @return array<string, mixed>
     */
    private function getPostData(): array
    {
        $request = $this->getRequest();

        return $request instanceof \Magento\Framework\App\Request\Http ? (array)$request->getPostValue() : [];
    }
}
