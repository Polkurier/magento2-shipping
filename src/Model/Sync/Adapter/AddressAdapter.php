<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sync\Adapter;

use Polkurier\Shipping\Api\ApiEventEnum;
use Polkurier\Shipping\Model\ApiEventDispatcher;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Model\AddressFactory;
use Polkurier\Shipping\Model\Mapper\AddressBookMapper;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Shipping\Model\Sync\AdapterInterface;
use Polkurier\Webservice\Enum\Address\AddressTypeEnum;
use Polkurier\Webservice\Request\Address\GetMessage;

/**
 * Sender addresses of the address book. Recipient entries are left to the Polkurier panel.
 */
class AddressAdapter implements AdapterInterface
{
    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly AddressBookMapper $mapper,
        private readonly CollectionFactory $collectionFactory,
        private readonly AddressFactory $addressFactory,
        private readonly AddressRepositoryInterface $repository,
        private readonly ApiEventDispatcher $events,
    ) {}

    public function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::Address;
    }

    public function fetchRemote(int $storeId): array
    {
        $remote = [];
        foreach ($this->clientPool->addresses($storeId)->getAddresses(new GetMessage(AddressTypeEnum::Sender)) as $address) {
            if ($address->getId() !== null) {
                $remote[$address->getId()] = $this->mapper->normalize($address);
            }
        }

        return $remote;
    }

    public function fetchLocal(int $websiteId): array
    {
        return \array_values($this->collectionFactory->create()
            ->addFieldToFilter(SyncableInterface::WEBSITE_ID, ['eq' => $websiteId])
            ->addFieldToFilter(AddressInterface::TYPE, AddressInterface::TYPE_SENDER)
            ->getItems());
    }

    public function createLocal(int $websiteId): SyncableInterface
    {
        return $this->addressFactory->create()->setWebsiteId($websiteId);
    }

    public function applyRemote(SyncableInterface $local, array $remote): void
    {
        /** @var AddressInterface $local */
        $this->mapper->apply($remote, $local);
    }

    public function canPush(): bool
    {
        return true;
    }

    public function push(SyncableInterface $local, int $storeId): array
    {
        /** @var AddressInterface $local */
        $request = $this->mapper->toSaveMessage($local);
        $isNew = $local->getExtId() === null;
        $response = $this->clientPool->addresses($storeId)->saveAddress($request);
        $this->events->dispatch(ApiEventEnum::AddressSaveAfter, $storeId, [
            'address' => $local,
            'is_new' => $isNew,
            'request' => $request,
            'response' => $response,
        ]);

        return $this->mapper->normalize($response);
    }

    public function deleteRemote(SyncableInterface $local, int $storeId): void
    {
        /** @var AddressInterface $local */
        $request = $this->mapper->toDeleteMessage($local);
        if ($this->clientPool->addresses($storeId)->deleteAddress($request)) {
            $this->events->dispatch(ApiEventEnum::AddressDeleteAfter, $storeId, ['address' => $local, 'request' => $request]);
        }
    }

    public function save(SyncableInterface $local): void
    {
        /** @var AddressInterface $local */
        $this->repository->save($local);
    }

    public function delete(SyncableInterface $local): void
    {
        /** @var AddressInterface $local */
        $this->repository->delete($local);
    }
}
