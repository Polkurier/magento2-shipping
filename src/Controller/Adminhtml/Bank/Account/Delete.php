<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bank\Account;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;
use Psr\Log\LoggerInterface;

class Delete extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';

    /**
     * @param BankAccountRepositoryInterface $bankAccountRepository
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        /**
         * @var BankAccountRepositoryInterface
         */
        private readonly BankAccountRepositoryInterface $bankAccountRepository,

        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        Context $context
    ) {
        parent::__construct($resultPageFactory, $resultForwardFactory, $resultJsonFactory, $logger, $context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $error = false;
        try {
            $this->bankAccountRepository->deleteById($this->getRequest()->getParam('id', false));
            $message = \__('You deleted the account number.');
        } catch (\Exception $e) {
            $error = true;
            $message = \__('We can\'t delete the account number right now.');
            $this->logger->error($e);
        }

        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData(['message' => $message, 'error' => $error]);

        return $resultJson;
    }
}
