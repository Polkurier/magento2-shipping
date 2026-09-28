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
use Polkurier\Shipping\Model\Sync\AdapterInterface;

class PackTemplateAdapter implements AdapterInterface
{
    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly PackTemplateMapper $mapper,
        private readonly CollectionFactory $collectionFactory,
        private readonly PackTemplateFactory $packTemplateFactory,
        private readonly PackTemplateRepositoryInterface $repository,
        private readonly ApiEventDispatcher $events,
    ) {}

    public function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::PackTemplate;
    }

    public function fetchRemote(int $storeId): array
    {
        $remote = [];
        foreach ($this->clientPool->packTemplates($storeId)->getPackTemplates() as $template) {
            if ($template->getId() !== null) {
                $remote[$template->getId()] = $this->mapper->normalize($template);
            }
        }

        return $remote;
    }

    public function fetchLocal(int $websiteId): array
    {
        return \array_values($this->collectionFactory->create()
            ->addFieldToFilter(SyncableInterface::WEBSITE_ID, ['eq' => $websiteId])
            ->getItems());
    }

    public function createLocal(int $websiteId): SyncableInterface
    {
        return $this->packTemplateFactory->create()->setWebsiteId($websiteId);
    }

    public function applyRemote(SyncableInterface $local, array $remote): void
    {
        /** @var PackTemplateInterface $local */
        $this->mapper->apply($remote, $local);
    }

    public function canPush(): bool
    {
        return true;
    }

    public function push(SyncableInterface $local, int $storeId): array
    {
        /** @var PackTemplateInterface $local */
        $request = $this->mapper->toSaveMessage($local);
        $isNew = $local->getExtId() === null;
        $response = $this->clientPool->packTemplates($storeId)->savePackTemplates($request);
        $this->events->dispatch(ApiEventEnum::PackTemplateSaveAfter, $storeId, [
            'pack_template' => $local,
            'is_new' => $isNew,
            'request' => $request,
            'response' => $response,
        ]);

        return $this->mapper->normalize($response);
    }

    public function deleteRemote(SyncableInterface $local, int $storeId): void
    {
        /** @var PackTemplateInterface $local */
        $request = $this->mapper->toDeleteMessage($local);
        if ($this->clientPool->packTemplates($storeId)->deletePackTemplates($request)) {
            $this->events->dispatch(ApiEventEnum::PackTemplateDeleteAfter, $storeId, ['pack_template' => $local, 'request' => $request]);
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
