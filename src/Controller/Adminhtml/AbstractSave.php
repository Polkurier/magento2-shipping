<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;

/**
 * Saves the entry and sends it to Polkurier. A refusal from Polkurier keeps the entry, marked with
 * the error, so nothing typed is lost.
 */
abstract class AbstractSave extends AbstractAction implements HttpPostActionInterface
{
    use ReadsPostDataTrait;

    public function __construct(
        Context $context,
        EntityHandlerInterface $entityHandler,
        private readonly DataPersistorInterface $dataPersistor,
    ) {
        parent::__construct($context, $entityHandler);
    }

    public function execute(): Redirect
    {
        $data = $this->getPostData();
        $redirect = $this->resultRedirectFactory->create();
        $id = (int)($data['id'] ?? 0);
        unset($data['id'], $data['form_key']);

        try {
            $entity = $id ? $this->entityHandler->get($id) : $this->entityHandler->create();
            $result = $this->entityHandler->save($entity, $data);
            if ($result->failed > 0 || $entity->getSyncError()) {
                $this->messageManager->addWarningMessage((string)__(
                    'Saved in Magento, but not in Polkurier: %1',
                    $entity->getSyncError() ?: $this->formatReasons($result),
                ));
            } elseif ($result->unconfirmed > 0) {
                $this->messageManager->addNoticeMessage((string)__(
                    'Saved in Magento. It goes to Polkurier once you confirm it with the code or your security PIN.',
                ));
            } else {
                $this->messageManager->addSuccessMessage((string)__('Saved.'));
            }
            $this->dataPersistor->clear($this->entityHandler->getFormMemory()->value);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set(
                $this->entityHandler->getFormMemory()->value,
                \array_diff_key($data, \array_flip($this->getTransientFields())),
            );

            return $redirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
        }

        return $this->isSaveAndContinue()
            ? $redirect->setPath('*/*/edit', ['id' => $entity->getId()])
            : $redirect->setPath('*/*/index');
    }

    /**
     * Posted fields that must not be kept to refill the form after an error, e.g. a PIN.
     *
     * @return string[]
     */
    protected function getTransientFields(): array
    {
        return [];
    }
}
