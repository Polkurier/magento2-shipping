<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync\Adapter;

use Polkurier\Shipping\Api\ApiEventEnum;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Model\Mapper\PackTemplateMapper;
use Polkurier\Shipping\Model\PackTemplateFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\Sync\PushableAdapterInterface;

readonly class PackTemplateAdapter implements PushableAdapterInterface
{
    public function __construct(
        private ClientPool $clientPool,
        private PackTemplateMapper $mapper,
        private CollectionFactory $collectionFactory,
        private PackTemplateFactory $packTemplateFactory,
        private PackTemplateRepositoryInterface $repository,
        private ApiEventDispatcher $events,
    ) {}

    public function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::PackTemplate;
    }

    public function fetchRemote(): array
    {
        $remote = [];
        foreach ($this->clientPool->packTemplates()->getPackTemplates() as $template) {
            if ($template->getId() !== null) {
                $remote[$template->getId()] = $this->mapper->normalize($template);
            }
        }

        return $remote;
    }

    public function fetchLocal(): array
    {
        return \array_values($this->collectionFactory->create()
            ->getItems());
    }

    public function createLocal(): SyncableInterface
    {
        return $this->packTemplateFactory->create();
    }

    public function applyRemote(SyncableInterface $local, array $remote): void
    {
        /** @var PackTemplateInterface $local */
        $this->mapper->apply($remote, $local);
    }

    public function getNaturalKey(SyncableInterface|array $entry): ?string
    {
        /** @var PackTemplateInterface|array<string, mixed> $entry */
        $value = \is_array($entry) ? (string)($entry['name'] ?? '') : $entry->getName();
        $key = (string)\mb_strtolower(\trim($value));

        return $key !== '' ? $key : null;
    }


    public function push(SyncableInterface $local): array
    {
        /** @var PackTemplateInterface $local */
        $request = $this->mapper->toSaveMessage($local);
        $isNew = $local->getExtId() === null;
        $response = $this->clientPool->packTemplates()->savePackTemplates($request);
        $this->events->dispatch(ApiEventEnum::PackTemplateSaveAfter, null, [
            'pack_template' => $local,
            'is_new' => $isNew,
            'request' => $request,
            'response' => $response,
        ]);

        return $this->mapper->normalize($response);
    }

    public function deleteRemote(SyncableInterface $local): void
    {
        /** @var PackTemplateInterface $local */
        $request = $this->mapper->toDeleteMessage($local);
        if ($this->clientPool->packTemplates()->deletePackTemplates($request)) {
            $this->events->dispatch(ApiEventEnum::PackTemplateDeleteAfter, null, ['pack_template' => $local, 'request' => $request]);
        }
    }

    public function save(SyncableInterface $local): void
    {
        /** @var PackTemplateInterface $local */
        $this->repository->save($local);
    }

    public function delete(SyncableInterface $local): void
    {
        /** @var PackTemplateInterface $local */
        $this->repository->delete($local);
    }
}
