<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\BankAccount\Publisher;
use Polkurier\Shipping\Model\WebsiteScope;

/**
 * Has Polkurier send the two-factor code that confirms saving a bank account.
 */
class SendCode extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Publisher $publisher,
        private readonly WebsiteScope $websiteScope,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        try {
            $token = $this->publisher->requestCode($this->websiteScope->getStoreId((int)($data['website_id'] ?? 0)));
        } catch (LocalizedException $e) {
            return $this->jsonFactory->create()->setData(['error' => true, 'message' => $e->getMessage()]);
        }

        return $this->jsonFactory->create()->setData([
            'error' => false,
            'token' => $token,
            'message' => (string)\__('Code sent. Enter it below, then save.'),
        ]);
    }
}
