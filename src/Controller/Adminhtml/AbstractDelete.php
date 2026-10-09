<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\BankAccount\BankAccountHandler;

/**
 * Deletes one entry of a list synchronized with Polkurier, and says so when Polkurier hasn't
 * deleted it yet.
 */
abstract class AbstractDelete extends AbstractAction implements HttpPostActionInterface
{
    use ReadsPostDataTrait;

    public function execute(): Redirect
    {
        $id = (int)$this->getRequest()->getParam('id');
        // Given only on the bank account form, for an account that locks bank accounts with a PIN.
        $pin = (string)($this->getPostData()[BankAccountHandler::FIELD_CONFIRMATION_PIN] ?? '');
        $gone = [];
        try {
            $result = $this->entityHandler->delete($this->entityHandler->get($id), $pin);
            // Its edit page is gone with it.
            $gone = ['/edit/id/' . $id . '/'];
            $result->failed > 0
                ? $this->messageManager->addWarningMessage(__(
                    'Deleted in Magento, but Polkurier hasn\'t deleted it yet: '
                    . '%1 It\'s tried again with the next synchronization.',
                    $this->reasons($result),
                ))
                : $this->messageManager->addSuccessMessage(__('Deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->returnBack('*/*/index', [], $gone);
    }
}
