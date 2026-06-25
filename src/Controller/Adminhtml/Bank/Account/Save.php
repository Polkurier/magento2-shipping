<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bank\Account;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\PageFactory;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;
use Polkurier\Shipping\Exception\NotFoundException;
use Polkurier\Shipping\Model\BankAccount;
use Polkurier\Shipping\Model\BankAccountFactory;
use Psr\Log\LoggerInterface;

class Save extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::bank_accounts';
    public const string DATA_PERSISTOR_KEY = 'polkurier_bank_account';

    /**
     * @param BankAccountRepositoryInterface $bankAccountRepository
     * @param BankAccountFactory $bankAccountFactory
     * @param DataPersistorInterface $dataPersistor
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        private readonly BankAccountRepositoryInterface $bankAccountRepository,
        private readonly BankAccountFactory $bankAccountFactory,
        private readonly DataPersistorInterface $dataPersistor,
        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        Context $context
    ) {
        parent::__construct($resultPageFactory, $resultForwardFactory, $resultJsonFactory, $logger, $context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $bankAccountData = $this->getRequest()->getPostValue();
        if (!$bankAccountData) {
            return $resultRedirect->setPath('*/*/');
        }

        $bankAccountId = (int)($bankAccountData[BankAccount::FIELD_ACCOUNT_ID] ?? 0);
        try {
            if ($bankAccountId) {
                $bankAccount = $this->bankAccountRepository->findOneById($bankAccountId);
                if (!$bankAccount) {
                    throw new NotFoundException(\__('This account number no longer exists.'));
                }
            } else {
                $bankAccount = $this->bankAccountFactory->create();
            }

            $bankAccount->addData($bankAccountData);
            $this->bankAccountRepository->save($bankAccount);
            $this->messageManager->addSuccessMessage(\__('The account number has been saved.'));
            $this->dataPersistor->clear(static::DATA_PERSISTOR_KEY);
            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $bankAccount->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (NotFoundException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager
                ->addExceptionMessage($e, \__('Something went wrong while saving the account number.'));
        }

        $this->dataPersistor->set(static::DATA_PERSISTOR_KEY, $bankAccountData);

        return $resultRedirect->setPath('*/*/edit', ['id' => $bankAccountId ?: null]);
    }
}
