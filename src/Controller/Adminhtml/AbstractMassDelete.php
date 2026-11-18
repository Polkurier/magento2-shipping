<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;

/**
 * Deletes the entries selected in the grid of a list synchronized with Polkurier, saying which were refused.
 */
abstract class AbstractMassDelete extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        EntityHandlerInterface $entityHandler,
        private readonly Filter $filter,
        private readonly FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $entityHandler);
    }

    /**
     * Deletes each entry on its own, so one that Polkurier refuses to delete doesn't stop the
     * others. The outcome is reported in one message.
     */
    public function execute(): Redirect
    {
        $deleted = 0;
        $refused = [];
        $failed = 0;
        $errors = [];
        $flash = $this->flashMessageCollectorFactory->create();
        try {
            /** @var SyncableInterface $entity */
            foreach ($this->filter->getCollection($this->createCollection()) as $entity) {
                try {
                    $result = $this->entityHandler->delete($entity);
                    $failed += $result->failed;
                    $errors = \array_merge($errors, $result->errors);
                    $deleted++;
                } catch (LocalizedException $e) {
                    $refused[] = $e->getMessage();
                }
            }
        } catch (LocalizedException $e) {
            $flash->addError($e->getMessage());
        }
        if ($deleted > 0 || $refused === []) {
            $flash->addSuccess(__('%1 deleted.', $deleted));
        }
        if ($failed > 0) {
            $flash->addWarning(__(
                'Polkurier hasn\'t deleted %1 of them yet: %2 They\'re tried again with the next synchronization.',
                $failed,
                \implode(' ', \array_unique($errors)),
            ));
        }
        if ($refused !== []) {
            $notDeleted = __('%1 not deleted: %2', \count($refused), \implode(' ', \array_unique($refused)));
            $deleted > 0 ? $flash->addWarning($notDeleted) : $flash->addError($notDeleted);
        }
        $flash->send($this->messageManager);

        return $this->returnBack('*/*/index');
    }

    abstract protected function createCollection(): AbstractDb;
}
