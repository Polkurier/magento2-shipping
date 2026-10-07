<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\Sync\PushableAdapterInterface;

/**
 * The fake adapter that also sends: $state('log', 'push:<name>') and 'delete:<id>' record it.
 */
class FakePushableSyncAdapter extends FakeSyncAdapter implements PushableAdapterInterface
{
    public function push(SyncableInterface $local): array
    {
        \assert($local instanceof PackTemplate);
        ($this->state)('log', 'push:' . $local->getName());

        return [
            SyncableInterface::FIELD_EXT_ID => $local->getExtId() ?? 'new-' . $local->getId(),
            SyncableInterface::FIELD_NAME => $local->getName(),
        ];
    }

    public function deleteRemote(SyncableInterface $local): void
    {
        ($this->state)('log', 'delete:' . $local->getExtId());
    }
}
