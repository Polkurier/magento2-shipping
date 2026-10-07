<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
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
use Polkurier\Shipping\Model\Admin\FlashMessageCollector;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;
use Polkurier\Shipping\Model\Sync\SyncResult;
use Polkurier\Shipping\Model\Sync\SyncResultFactory;

/**
 * Deletes the entries selected in the grid of a list synchronized with Polkurier, saying which were refused.
 */
abstract class AbstractMassDelete extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        EntityHandlerInterface $handler,
        private readonly Filter $filter,
        FlashMessageCollectorFactory $flashMessageCollectorFactory,
        private readonly SyncResultFactory $syncResultFactory,
    ) {
        parent::__construct($context, $handler, $flashMessageCollectorFactory);
    }

    /**
     * Each entry on its own: one that Polkurier refuses to delete doesn't keep the others. All of
     * it is told in one message.
     */
    public function execute(): Redirect
    {
        $deleted = 0;
        $refused = [];
        $synced = null;
        $flash = $this->flashMessageCollectorFactory->create();
        try {
            /** @var SyncableInterface $entity */
            foreach ($this->filter->getCollection($this->createCollection()) as $entity) {
                try {
                    $result = $this->handler->delete($entity);
                    $synced ??= $this->syncResultFactory->create(['entityType' => $result->entityType]);
                    $synced->add($result);
                    $deleted++;
                } catch (LocalizedException $e) {
                    $refused[] = $e->getMessage();
                }
            }
        } catch (LocalizedException $e) {
            $flash->error($e->getMessage());
        }
        if ($deleted > 0 || $refused === []) {
            $flash->success(__('%1 deleted.', $deleted));
        }
        if ($synced !== null && $synced->failed > 0) {
            $flash->warning(__(
                'Polkurier hasn\'t deleted %1 of them yet: %2 They\'re tried again with the next synchronization.',
                $synced->failed,
                $this->reasons($synced),
            ));
        }
        if ($refused !== []) {
            $notDeleted = __('%1 not deleted: %2', \count($refused), \implode(' ', \array_unique($refused)));
            $deleted > 0 ? $flash->warning($notDeleted) : $flash->error($notDeleted);
        }
        $flash->send($this->messageManager);

        return $this->returnBack('*/*/index');
    }

    abstract protected function createCollection(): AbstractDb;
}
