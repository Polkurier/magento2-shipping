<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Framework\Event\ManagerInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;

/**
 * Keeps every dispatched event, and can make listeners fail.
 */
class RecordingEventManager implements ManagerInterface
{
    /**
     * @var array<int, array{name: string, data: array<string, mixed>}>
     */
    public array $dispatched = [];

    public bool $failing = false;

    /**
     * @param string $eventName
     * @param array<string, mixed> $data
     */
    public function dispatch($eventName, array $data = []): void
    {
        $this->dispatched[] = ['name' => $eventName, 'data' => $data];
        if ($this->failing) {
            throw new \RuntimeException('Listener failed');
        }
    }

    /**
     * @return array<int, array<string, mixed>> the data of each dispatch of the event
     */
    public function listDispatched(ApiEventEnum $event): array
    {
        return \array_values(\array_map(
            static fn(array $dispatch): array => $dispatch['data'],
            \array_filter($this->dispatched, static fn(array $dispatch): bool => $dispatch['name'] === $event->value),
        ));
    }
}
