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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\MoneyBox\Payouts;

/**
 * Withdraws a payout request Polkurier has not sent yet.
 */
class Cancel extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::money_box_payout';

    public function __construct(
        Context $context,
        private readonly Payouts $payouts,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $number = (string)$this->getRequest()->getParam('number');
        try {
            if ($number === '') {
                throw new LocalizedException(\__('No payout to cancel.'));
            }
            $this->payouts->cancelPayout($number);
            $this->messageManager->addSuccessMessage(\__('Payout %1 cancelled.', $number));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
