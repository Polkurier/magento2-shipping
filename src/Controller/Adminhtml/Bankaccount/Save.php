<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Bankaccount;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractSave;
use Polkurier\Shipping\Model\Admin\BankAccountHandler;

/**
 * Saves a bank account, or, from the form's Delete button, deletes it with the PIN given there.
 */
class Save extends AbstractSave
{
    public function __construct(
        Context $context,
        private readonly BankAccountHandler $bankAccountHandler,
        DataPersistorInterface $dataPersistor,
    ) {
        parent::__construct($context, $bankAccountHandler, $dataPersistor);
    }

    public function execute(): Redirect
    {
        if (!$this->getRequest()->getParam('delete')) {
            return parent::execute();
        }
        $id = (int)$this->getRequest()->getParam('id');
        $redirect = $this->resultRedirectFactory->create();
        try {
            $pin = \trim((string)$this->getRequest()->getParam('confirmation_pin')) ?: null;
            $this->bankAccountHandler->delete($this->bankAccountHandler->get($id), $pin);
            $this->messageManager->addSuccessMessage(\__('Deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/edit', ['id' => $id]);
        }

        return $redirect->setPath('*/*/index');
    }

    protected function getTransientFields(): array
    {
        return BankAccountHandler::CONFIRMATION_FIELDS;
    }
}
