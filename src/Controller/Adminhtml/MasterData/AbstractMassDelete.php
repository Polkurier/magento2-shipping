<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\MasterData;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\Admin\EntityHandlerInterface;
use Polkurier\Shipping\Model\Admin\Flash;

abstract class AbstractMassDelete extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        EntityHandlerInterface $handler,
        private readonly Filter $filter,
    ) {
        parent::__construct($context, $handler);
    }

    abstract protected function createCollection(): AbstractDb;

    /**
     * Each entry on its own: one that Polkurier refuses to delete doesn't keep the others. All of
     * it is told in one message.
     */
    public function execute(): Redirect
    {
        $deleted = 0;
        $refused = [];
        $synced = null;
        $flash = new Flash();
        try {
            /** @var SyncableInterface $entity */
            foreach ($this->filter->getCollection($this->createCollection()) as $entity) {
                try {
                    $result = $this->handler->delete($entity);
                    $synced = $synced === null ? $result : $synced->add($result);
                    $deleted++;
                } catch (LocalizedException $e) {
                    $refused[] = $e->getMessage();
                }
            }
        } catch (LocalizedException $e) {
            $flash->error($e->getMessage());
        }
        if ($deleted > 0 || $refused === []) {
            $flash->success(\__('%1 deleted.', $deleted));
        }
        if ($synced !== null && $synced->failed > 0) {
            $flash->warning(\__(
                'Polkurier hasn\'t deleted %1 of them yet: %2 They\'re tried again with the next synchronization.',
                $synced->failed,
                $this->reasons($synced),
            ));
        }
        if ($refused !== []) {
            $notDeleted = \__('%1 not deleted: %2', \count($refused), \implode(' ', \array_unique($refused)));
            $deleted > 0 ? $flash->warning($notDeleted) : $flash->error($notDeleted);
        }
        $flash->send($this->messageManager);

        return $this->returnBack('*/*/index');
    }
}
