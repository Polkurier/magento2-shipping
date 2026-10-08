<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\Event\ManagerInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Logger\Logger;

/**
 * Dispatches the events of {@see ApiEventEnum}.
 *
 * They report changes Polkurier has already made, so a listener's exception is logged instead of
 * thrown: the caller's outcome stands whatever a listener does.
 */
readonly class ApiEventDispatcher
{
    public function __construct(
        private ManagerInterface $eventManager,
        private Logger $logger,
    ) {}

    /**
     * @param int|null $storeId the order's store; null for the account's entries, and for a shipment
     *     whose store view was deleted since
     * @param array<string, mixed> $data
     */
    public function dispatch(ApiEventEnum $event, ?int $storeId, array $data): void
    {
        try {
            $this->eventManager->dispatch($event->value, ['store_id' => $storeId] + $data);
        } catch (\Throwable $e) {
            $this->logger->error('A listener of ' . $event->value . ' failed', ['exception' => $e]);
        }
    }
}
