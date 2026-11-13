<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Address;

use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Enum\Sync\SyncEntityEnum;
use Polkurier\Shipping\Model\AddressFactory;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Sync\MatchedByNameTrait;
use Polkurier\Shipping\Model\Sync\PushableAdapterInterface;
use Polkurier\Webservice\Dto\Request\Address\GetMessage;
use Polkurier\Webservice\Enum\Address\AddressTypeEnum;

/**
 * Sender addresses of the address book. Recipient entries are left to the Polkurier panel.
 */
readonly class AddressSyncAdapter implements PushableAdapterInterface
{
    use MatchedByNameTrait;

    public function __construct(
        private PolkurierClientInterface $polkurierClient,
        private AddressBookMapper $addressBookMapper,
        private CollectionFactory $collectionFactory,
        private AddressFactory $addressFactory,
        private AddressRepositoryInterface $addressRepository,
        private ApiEventDispatcher $apiEventDispatcher,
    ) {}

    public function getEntityType(): SyncEntityEnum
    {
        return SyncEntityEnum::Address;
    }

    public function fetchRemote(): array
    {
        $remote = [];
        $addresses = $this->polkurierClient->getAddresses()->getAddresses(new GetMessage(AddressTypeEnum::Sender));
        foreach ($addresses as $address) {
            if ($address->getId() !== null) {
                $remote[$address->getId()] = $this->addressBookMapper->normalize($address);
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
        $this->addressBookMapper->apply($remote, $local);
    }

    public function push(SyncableInterface $local): array
    {
        /** @var AddressInterface $local */
        $request = $this->addressBookMapper->mapToRecord($local);
        $isNew = $local->getExtId() === null;
        $response = $this->polkurierClient->getAddresses()->saveAddress($request);
        $this->apiEventDispatcher->dispatch(ApiEventEnum::AddressSaveAfter, null, [
            'address' => $local,
            'is_new' => $isNew,
            'request' => $request,
            'response' => $response,
        ]);

        return $this->addressBookMapper->normalize($response);
    }

    public function deleteRemote(SyncableInterface $local): void
    {
        /** @var AddressInterface $local */
        $request = $this->addressBookMapper->mapToDeleteMessage($local);
        if ($this->polkurierClient->getAddresses()->deleteAddress($request)) {
            $this->apiEventDispatcher->dispatch(
                ApiEventEnum::AddressDeleteAfter,
                null,
                ['address' => $local, 'request' => $request],
            );
        }
    }

    public function save(SyncableInterface $local): void
    {
        /** @var AddressInterface $local */
        $this->addressRepository->save($local);
    }

    public function delete(SyncableInterface $local): void
    {
        /** @var AddressInterface $local */
        $this->addressRepository->delete($local);
    }
}
