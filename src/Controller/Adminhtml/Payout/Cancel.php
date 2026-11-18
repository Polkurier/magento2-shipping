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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Controller\Adminhtml\ReturnsBackTrait;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Shipping\Model\RequestParamReader;

/**
 * Withdraws a payout request Polkurier has not sent yet.
 */
class Cancel extends Action implements HttpPostActionInterface
{
    use ReturnsBackTrait;

    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::payout_request';
    public const string ROUTE = 'polkurier/payout/cancel';

    public function __construct(
        Context $context,
        private readonly PayoutManager $payoutManager,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $number = RequestParamReader::getString($this->getRequest(), 'number');
        try {
            if ($number === '') {
                throw new LocalizedException(__('No payout to cancel.'));
            }
            $this->payoutManager->cancelPayout($number);
            $this->messageManager->addSuccessMessage(__('Payout %1 cancelled.', $number));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->returnBack('*/*/index');
    }
}
