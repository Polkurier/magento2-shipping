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
use Polkurier\Shipping\Model\Admin\SyncableEntityHandler;

abstract class AbstractMassDelete extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        SyncableEntityHandler $handler,
        private readonly Filter $filter,
    ) {
        parent::__construct($context, $handler);
    }

    abstract protected function createCollection(): AbstractDb;

    public function execute(): Redirect
    {
        try {
            $deleted = 0;
            /** @var SyncableInterface $entity */
            foreach ($this->filter->getCollection($this->createCollection()) as $entity) {
                $this->handler->delete($entity);
                $deleted++;
            }
            $this->messageManager->addSuccessMessage(\__('%1 deleted.', $deleted));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
