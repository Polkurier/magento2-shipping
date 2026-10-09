<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;

/**
 * Saves the rows edited in a list as each row's form would. Rows go to Polkurier straight away,
 * except bank accounts: they wait for confirmation, since a list has no place for the PIN or code.
 */
abstract class AbstractInlineEdit extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        EntityHandlerInterface $entityHandler,
        private readonly JsonFactory $jsonFactory,
    ) {
        parent::__construct($context, $entityHandler);
    }

    public function execute(): Json
    {
        $messages = [];
        foreach ((array)$this->getRequest()->getParam('items', []) as $id => $row) {
            if (!\is_array($row)) {
                continue;
            }
            try {
                $entity = $this->entityHandler->get((int)$id);
                $editable = \array_intersect_key($row, \array_flip($this->getEditableFields()));
                $this->entityHandler->save($entity, $editable);
                // Saved here either way. The merchant needs to see Polkurier's refusal, or that the
                // entry still awaits confirmation.
                if ($entity->getSyncState() === SyncableInterface::STATE_ERROR) {
                    $messages[] = (string)__('#%1: %2', $id, (string)$entity->getSyncError());
                } elseif ($entity->getSyncState() === SyncableInterface::STATE_UNCONFIRMED) {
                    $messages[] = (string)__(
                        '#%1: Saved in Magento. It goes to Polkurier once you confirm it: '
                        . 'open it and save it with the code or your security PIN.',
                        $id,
                    );
                }
            } catch (LocalizedException $e) {
                $messages[] = $e->getMessage();
            }
        }

        return $this->jsonFactory->create()->setData(['messages' => $messages, 'error' => $messages !== []]);
    }

    /**
     * The columns the list lets the merchant change.
     *
     * @return string[]
     */
    abstract protected function getEditableFields(): array;
}
