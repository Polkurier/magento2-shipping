<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Mapper;

use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Webservice\Enum\Address\AddressTypeEnum;
use Polkurier\Webservice\Request\Address\DeleteMessage;
use Polkurier\Webservice\Request\Address\SaveMessage;
use Polkurier\Webservice\Response\Address;

/**
 * Maps address book entries between Magento and the Polkurier account.
 */
readonly class AddressBookMapper
{
    /**
     * The fields both sides keep, as compared and copied by the synchronization.
     *
     * @return array<string, mixed>
     */
    public function normalize(Address $address): array
    {
        return [
            'id' => $address->getId(),
            'name' => $address->getName(),
            'type' => $address->getType()->value,
            'person' => $address->getPerson(),
            'company' => $address->getCompany() ?: null,
            'street' => $address->getStreet(),
            'house_number' => $address->getHouseNumber(),
            'flat_number' => $address->getFlatNumber() ?: null,
            'city' => $address->getCity(),
            'postcode' => $address->getPostcode(),
            'province' => $address->getProvince() ?: null,
            'country' => $address->getCountry() ?: Polkurier::HOME_COUNTRY,
            'point_id' => $address->getPointId() ?: null,
            'email' => $address->getEmail(),
            'phone' => $address->getPhone(),
            'default' => $address->isDefault(),
            'cover' => $address->isCoverAddress(),
            'shared' => $address->isShared(),
        ];
    }

    /**
     * What Polkurier can't keep stays as Magento has it: a sender's drop-off point (Polkurier
     * keeps points for recipients only) and a province it has none for (it knows no Polish
     * ones).
     *
     * @param array<string, mixed> $remote as returned by {@see normalize()}
     */
    public function apply(array $remote, AddressInterface $address): void
    {
        $sender = (string)$remote['type'] === AddressInterface::TYPE_SENDER;
        $address->setExtId(isset($remote['id']) ? (string)$remote['id'] : null)
            ->setName((string)$remote['name'])
            ->setType((string)$remote['type'])
            ->setPerson((string)$remote['person'])
            ->setCompany($remote['company'])
            ->setStreet((string)$remote['street'])
            ->setHouseNumber((string)$remote['house_number'])
            ->setFlatNumber($remote['flat_number'])
            ->setCity((string)$remote['city'])
            ->setPostcode((string)$remote['postcode'])
            ->setProvince(($remote['province'] ?? null) ?: $address->getProvince())
            ->setCountry((string)$remote['country'])
            ->setPointId($sender ? $address->getPointId() : $remote['point_id'])
            ->setEmail((string)$remote['email'])
            ->setPhone((string)$remote['phone'])
            ->setDefault((bool)$remote['default'])
            ->setCover((bool)$remote['cover'])
            ->setShared((bool)$remote['shared']);
    }

    public function toSaveMessage(AddressInterface $address): SaveMessage
    {
        return new SaveMessage(
            name: $address->getName(),
            person: $address->getPerson(),
            street: $address->getStreet(),
            houseNumber: $address->getHouseNumber(),
            postcode: $address->getPostcode(),
            city: $address->getCity(),
            email: $address->getEmail(),
            phone: $address->getPhone(),
            id: $address->getExtId(),
            type: AddressTypeEnum::tryFrom($address->getType()) ?? AddressTypeEnum::Sender,
            company: $address->getCompany() ?: null,
            flatNumber: $address->getFlatNumber() ?: null,
            province: $address->getProvince() ?: null,
            country: $address->getCountry() ?: Polkurier::HOME_COUNTRY,
            pointId: $address->getPointId() ?: null,
            default: $address->isDefault(),
            coverAddress: $address->isCover(),
        );
    }

    public function toDeleteMessage(AddressInterface $address): DeleteMessage
    {
        return new DeleteMessage(
            id: (string)$address->getExtId(),
            type: AddressTypeEnum::tryFrom($address->getType()) ?? AddressTypeEnum::Sender,
        );
    }
}
