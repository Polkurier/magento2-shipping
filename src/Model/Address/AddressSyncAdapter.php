<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Address;

use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\ApiEventEnum;
use Polkurier\Shipping\Enum\SyncEntityEnum;
use Polkurier\Shipping\Model\AddressFactory;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sync\MatchedByNameTrait;
use Polkurier\Shipping\Model\Sync\PushableAdapterInterface;
use Polkurier\Webservice\Enum\Address\AddressTypeEnum;
use Polkurier\Webservice\Request\Address\GetMessage;

/**
 * Sender addresses of the address book. Recipient entries are left to the Polkurier panel.
 */
readonly class AddressSyncAdapter implements PushableAdapterInterface
{
    use MatchedByNameTrait;

    public function __construct(
        private PolkurierClientInterface $client,
        private AddressBookMapper $mapper,
        private CollectionFactory $collectionFactory,
        private AddressFactory $addressFactory,
        private AddressRepositoryInterface $repository,
        private ApiEventDispatcher $events,
    ) {}

    public function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::Address;
    }

    public function fetchRemote(): array
    {
        $remote = [];
        foreach ($this->client->addresses()->getAddresses(new GetMessage(AddressTypeEnum::Sender)) as $address) {
            if ($address->getId() !== null) {
                $remote[$address->getId()] = $this->mapper->normalize($address);
            }
        }

        return $remote;
    }

    public function fetchLocal(): array
    {
        return \array_values($this->collectionFactory->create()
            ->addFieldToFilter(AddressInterface::FIELD_TYPE, AddressInterface::TYPE_SENDER)
            ->getItems());
    }

    public function createLocal(): SyncableInterface
    {
        return $this->addressFactory->create();
    }

    public function applyRemote(SyncableInterface $local, array $remote): void
    {
        /** @var AddressInterface $local */
        $this->mapper->apply($remote, $local);
    }

    public function push(SyncableInterface $local): array
    {
        /** @var AddressInterface $local */
        $request = $this->mapper->toSaveMessage($local);
        $isNew = $local->getExtId() === null;
        $response = $this->client->addresses()->saveAddress($request);
        $this->events->dispatch(ApiEventEnum::AddressSaveAfter, null, [
            'address' => $local,
            'is_new' => $isNew,
            'request' => $request,
            'response' => $response,
        ]);

        return $this->mapper->normalize($response);
    }

    public function deleteRemote(SyncableInterface $local): void
    {
        /** @var AddressInterface $local */
        $request = $this->mapper->toDeleteMessage($local);
        if ($this->client->addresses()->deleteAddress($request)) {
            $this->events->dispatch(
                ApiEventEnum::AddressDeleteAfter,
                null,
                ['address' => $local, 'request' => $request],
            );
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
