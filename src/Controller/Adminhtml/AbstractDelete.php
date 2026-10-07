<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;

/**
 * Deletes one entry of a list synchronized with Polkurier, saying when Polkurier has yet to delete it too.
 */
abstract class AbstractDelete extends AbstractAction implements HttpPostActionInterface
{
    public function execute(): Redirect
    {
        $id = (int)$this->getRequest()->getParam('id');
        $gone = [];
        try {
            $result = $this->handler->delete($this->handler->get($id));
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
