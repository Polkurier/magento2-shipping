<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\MasterData;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\Admin\EntityHandlerInterface;

/**
 * Saves the rows edited in a list, each as its form would: sent to Polkurier straight away, and
 * for bank accounts left awaiting confirmation, since a list has no place for the PIN or code.
 */
abstract class AbstractInlineEdit extends AbstractAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        EntityHandlerInterface $handler,
        private readonly JsonFactory $jsonFactory,
    ) {
        parent::__construct($context, $handler);
    }

    /**
     * The columns the list lets the merchant change.
     *
     * @return string[]
     */
    abstract protected function getEditableFields(): array;

    public function execute(): Json
    {
        $messages = [];
        foreach ((array)$this->getRequest()->getParam('items', []) as $id => $row) {
            if (!\is_array($row)) {
                continue;
            }
            try {
                $entity = $this->handler->get((int)$id);
                $this->handler->save($entity, \array_intersect_key($row, \array_flip($this->getEditableFields())));
                // Saved here either way; Polkurier's refusal, or that it's still waiting for a
                // confirmation, is what the merchant needs to see.
                $saved = $this->handler->get((int)$id);
                if ($saved->getSyncState() === SyncableInterface::STATE_ERROR) {
                    $messages[] = (string)\__('#%1: %2', $id, (string)$saved->getSyncError());
                } elseif ($saved->getSyncState() === SyncableInterface::STATE_UNCONFIRMED) {
                    $messages[] = (string)\__(
                        '#%1: Saved in Magento. It goes to Polkurier once you confirm it: open it and save it with the code or your security PIN.',
                        $id,
                    );
                }
            } catch (LocalizedException $e) {
                $messages[] = $e->getMessage();
            }
        }

        return $this->jsonFactory->create()->setData(['messages' => $messages, 'error' => $messages !== []]);
    }
}
