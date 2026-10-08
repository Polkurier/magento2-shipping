<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;

/**
 * Admin editing of one kind of synchronized entity: a change is saved locally, marked for
 * sending, and that entry alone is sent to Polkurier right away; the full two-way run is left to
 * the cron and the Synchronize button. Deleting is recorded first and carried out by the
 * synchronization, which also deletes the entry in Polkurier.
 *
 * One subclass per entity.
 */
abstract readonly class SyncableEntityHandler implements EntityHandlerInterface
{
    use EditsSyncableEntriesTrait;

    public function __construct(
        private PushableAdapterInterface $adapter,
        private SyncEngine $syncEngine,
        private Synchronization $synchronization,
        private ErrorMessageResolver $errorMessageResolver,
        protected DateTime $dateTime,
        private SyncLock $syncLock,
        private SyncResultFactory $syncResultFactory,
    ) {}

    public function create(): SyncableInterface
    {
        return $this->adapter->createLocal();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws LocalizedException
     */
    public function save(SyncableInterface $entity, array $data): SyncResult
    {
        return $this->syncLock->run(function () use ($entity, $data): SyncResult {
            $this->applyFormData($entity, $data);
            $this->adapter->save($entity);

            return $this->push($entity);
        });
    }

    /**
     * @throws LocalizedException
     */
    public function delete(SyncableInterface $entity, ?string $pin = null): SyncResult
    {
        return $this->syncLock->run(function () use ($entity): SyncResult {
            if ($entity->getExtId() === null) {
                // Never sent to Polkurier: nothing to delete there.
                $this->adapter->delete($entity);
                $result = $this->syncResultFactory->create(['entityType' => $this->adapter->getEntityType()]);
                $result->deleted++;

                return $result;
            }
            $entity->setDeletedAt($this->now())->setSyncState(SyncableInterface::STATE_PENDING);
            $this->adapter->save($entity);

            return $this->push($entity);
        });
    }

    /**
     * @return string[]
     * @throws LocalizedException
     */
    public function synchronize(): array
    {
        return $this->synchronization->synchronize([$this->adapter->getEntityType()]);
    }

    /**
     * Sends the entry just changed, and nothing else: no other entry is read or changed on either
     * side.
     *
     * @throws LocalizedException
     */
    private function push(SyncableInterface $entity): SyncResult
    {
        try {
            return $this->syncEngine->run($this->adapter, $entity);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('%1: %2', $this->adapter->getEntityType()->label(), $this->errorMessageResolver->phrase($e)),
                $e,
            );
        }
    }
}
