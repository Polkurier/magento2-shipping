<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PackTemplate;

use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Enum\Sync\SyncEntityEnum;
use Polkurier\Shipping\Model\PackTemplateFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sync\MatchedByNameTrait;
use Polkurier\Shipping\Model\Sync\PushableAdapterInterface;

/**
 * Pack templates, between Magento and the Polkurier account.
 */
readonly class PackTemplateSyncAdapter implements PushableAdapterInterface
{
    use MatchedByNameTrait;

    public function __construct(
        private PolkurierClientInterface $polkurierClient,
        private PackTemplateMapper $packTemplateMapper,
        private CollectionFactory $collectionFactory,
        private PackTemplateFactory $packTemplateFactory,
        private PackTemplateRepositoryInterface $packTemplateRepository,
        private ApiEventDispatcher $apiEventDispatcher,
    ) {}

    public function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::PackTemplate;
    }

    public function fetchRemote(): array
    {
        $remote = [];
        foreach ($this->polkurierClient->packTemplates()->getPackTemplates() as $template) {
            if ($template->getId() !== null) {
                $remote[$template->getId()] = $this->packTemplateMapper->normalize($template);
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
        $this->packTemplateMapper->apply($remote, $local);
    }

    public function push(SyncableInterface $local): array
    {
        /** @var PackTemplateInterface $local */
        $request = $this->packTemplateMapper->toSaveMessage($local);
        $isNew = $local->getExtId() === null;
        $response = $this->polkurierClient->packTemplates()->savePackTemplates($request);
        $this->apiEventDispatcher->dispatch(ApiEventEnum::PackTemplateSaveAfter, null, [
            'pack_template' => $local,
            'is_new' => $isNew,
            'request' => $request,
            'response' => $response,
        ]);

        return $this->packTemplateMapper->normalize($response);
    }

    public function deleteRemote(SyncableInterface $local): void
    {
        /** @var PackTemplateInterface $local */
        $request = $this->packTemplateMapper->toDeleteMessage($local);
        if ($this->polkurierClient->packTemplates()->deletePackTemplates($request)) {
            $this->apiEventDispatcher->dispatch(
                ApiEventEnum::PackTemplateDeleteAfter,
                null,
                ['pack_template' => $local, 'request' => $request],
            );
        }
    }

    public function save(SyncableInterface $local): void
    {
        /** @var PackTemplateInterface $local */
        $this->packTemplateRepository->save($local);
    }

    public function delete(SyncableInterface $local): void
    {
        /** @var PackTemplateInterface $local */
        $this->packTemplateRepository->delete($local);
    }
}
