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
     * Each entry on its own: one that Polkurier refuses to delete doesn't keep the others.
     */
    public function execute(): Redirect
    {
        $deleted = 0;
        $failed = 0;
        try {
            /** @var SyncableInterface $entity */
            foreach ($this->filter->getCollection($this->createCollection()) as $entity) {
                try {
                    $this->handler->delete($entity);
                    $deleted++;
                } catch (LocalizedException $e) {
                    $failed++;
                    $this->messageManager->addErrorMessage($e->getMessage());
                }
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        if ($failed === 0) {
            $this->messageManager->addSuccessMessage(\__('%1 deleted.', $deleted));
        } elseif ($deleted > 0) {
            $this->messageManager->addWarningMessage(\__('%1 deleted, %2 not deleted.', $deleted, $failed));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
