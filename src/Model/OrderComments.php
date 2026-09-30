<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Adds a comment to an order's history, saving the comment alone rather than the whole order,
 * which would save its addresses, items and payment again.
 */
class OrderComments
{
    public function __construct(
        private readonly OrderStatusHistoryRepositoryInterface $historyRepository,
    ) {}

    /**
     * @throws CouldNotSaveException
     */
    public function add(Order $order, Phrase|string $comment): void
    {
        $history = $order->addCommentToStatusHistory((string)$comment);
        // Declared to return the comment, without a return type.
        if ($history instanceof OrderStatusHistoryInterface) {
            $this->historyRepository->save($history);
        }
    }
}
