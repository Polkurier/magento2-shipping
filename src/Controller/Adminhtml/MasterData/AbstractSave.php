<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\MasterData;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Admin\EntityHandlerInterface;
use Polkurier\Shipping\Model\Admin\Flash;

/**
 * Saves the entry and sends it to Polkurier. A refusal from Polkurier keeps the entry, marked with
 * the error, so nothing typed is lost.
 */
abstract class AbstractSave extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        EntityHandlerInterface $handler,
        private readonly DataPersistorInterface $dataPersistor,
    ) {
        parent::__construct($context, $handler);
    }

    public function execute(): Redirect
    {
        $data = $this->getPostData();
        $redirect = $this->resultRedirectFactory->create();
        $id = (int)($data['id'] ?? 0);
        unset($data['id'], $data['form_key']);

        try {
            $entity = $id ? $this->handler->get($id) : $this->handler->create();
            $result = $this->handler->save($entity, $data);
            $entity = $this->handler->get((int)$entity->getId());
            $flash = new Flash();
            if ($result->failed > 0 || $entity->getSyncError()) {
                $flash->warning(\__('Saved in Magento, but not in Polkurier: %1', $entity->getSyncError() ?: $this->reasons($result)));
            } elseif ($result->unconfirmed > 0) {
                $flash->notice(\__('Saved in Magento. It goes to Polkurier once you confirm it with the code or your security PIN.'));
            } else {
                $flash->success(\__('Saved.'));
            }
            $flash->send($this->messageManager);
            $this->dataPersistor->clear($this->handler->getPersistorKey());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set(
                $this->handler->getPersistorKey(),
                \array_diff_key($data, \array_flip($this->getTransientFields())),
            );

            return $redirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
        }

        return $this->getRequest()->getParam('back')
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

    /**
     * @return array<string, mixed>
     */
    private function getPostData(): array
    {
        $request = $this->getRequest();

        return $request instanceof \Magento\Framework\App\Request\Http ? (array)$request->getPostValue() : [];
    }
}
