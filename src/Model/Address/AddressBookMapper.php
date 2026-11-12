<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Address;

use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Webservice\Builder\Address\SaveMessageBuilderFactory;
use Polkurier\Webservice\Enum\Address\AddressTypeEnum;
use Polkurier\Webservice\Request\Address\DeleteMessage;
use Polkurier\Webservice\Request\Address\SaveMessage;
use Polkurier\Webservice\Response\Address;

/**
 * Maps address book entries between Magento and the Polkurier account.
 */
readonly class AddressBookMapper
{
    public function __construct(
        private SaveMessageBuilderFactory $saveMessageBuilderFactory,
    ) {}

    /**
     * The fields both sides keep, as compared and copied by the synchronization.
     *
     * @return array<string, mixed>
     */
    public function normalize(Address $address): array
    {
        return [
            AddressInterface::FIELD_EXT_ID => $address->getId(),
            AddressInterface::FIELD_NAME => $address->getName(),
            AddressInterface::FIELD_TYPE => $address->getType()->value,
            AddressInterface::FIELD_PERSON => $address->getPerson(),
            AddressInterface::FIELD_COMPANY => $address->getCompany() ?: null,
            AddressInterface::FIELD_STREET => $address->getStreet(),
            AddressInterface::FIELD_HOUSE_NUMBER => $address->getHouseNumber(),
            AddressInterface::FIELD_FLAT_NUMBER => $address->getFlatNumber() ?: null,
            AddressInterface::FIELD_CITY => $address->getCity(),
            AddressInterface::FIELD_POSTCODE => $address->getPostcode(),
            AddressInterface::FIELD_PROVINCE => $address->getProvince() ?: null,
            AddressInterface::FIELD_COUNTRY => $address->getCountry() ?: Polkurier::HOME_COUNTRY,
            AddressInterface::FIELD_EMAIL => $address->getEmail(),
            AddressInterface::FIELD_PHONE => $address->getPhone(),
            AddressInterface::FIELD_IS_DEFAULT => $address->isDefault(),
            AddressInterface::FIELD_IS_COVER => $address->isCoverAddress(),
            AddressInterface::FIELD_SHARED => $address->isShared(),
        ];
    }

    /**
     * The province stays as Magento has it when Polkurier has none: Polkurier knows no Polish ones.
     *
     * @param array<string, mixed> $remote as returned by {@see normalize()}
     */
    public function apply(array $remote, AddressInterface $address): void
    {
        $extId = $remote[AddressInterface::FIELD_EXT_ID] ?? null;
        $address->setExtId($extId !== null ? (string)$extId : null)
            ->setName((string)$remote[AddressInterface::FIELD_NAME])
            ->setType((string)$remote[AddressInterface::FIELD_TYPE])
            ->setPerson((string)$remote[AddressInterface::FIELD_PERSON])
            ->setCompany($remote[AddressInterface::FIELD_COMPANY])
            ->setStreet((string)$remote[AddressInterface::FIELD_STREET])
            ->setHouseNumber((string)$remote[AddressInterface::FIELD_HOUSE_NUMBER])
            ->setFlatNumber($remote[AddressInterface::FIELD_FLAT_NUMBER])
            ->setCity((string)$remote[AddressInterface::FIELD_CITY])
            ->setPostcode((string)$remote[AddressInterface::FIELD_POSTCODE])
            ->setProvince(($remote[AddressInterface::FIELD_PROVINCE] ?? null) ?: $address->getProvince())
            ->setCountry((string)$remote[AddressInterface::FIELD_COUNTRY])
            ->setEmail((string)$remote[AddressInterface::FIELD_EMAIL])
            ->setPhone((string)$remote[AddressInterface::FIELD_PHONE])
            ->setDefault((bool)$remote[AddressInterface::FIELD_IS_DEFAULT])
            ->setCover((bool)$remote[AddressInterface::FIELD_IS_COVER])
            ->setShared((bool)$remote[AddressInterface::FIELD_SHARED]);
    }

    public function mapToSaveMessage(AddressInterface $address): SaveMessage
    {
        return $this->saveMessageBuilderFactory->create()
            ->name($address->getName())
            ->person($address->getPerson())
            ->street($address->getStreet())
            ->houseNumber($address->getHouseNumber())
            ->postcode($address->getPostcode())
            ->city($address->getCity())
            ->email($address->getEmail())
            ->phone($address->getPhone())
            ->id($address->getExtId())
            ->type(AddressTypeEnum::tryFrom($address->getType()) ?? AddressTypeEnum::Sender)
            ->company($address->getCompany() ?: null)
            ->flatNumber($address->getFlatNumber() ?: null)
            ->province($address->getProvince() ?: null)
            ->country($address->getCountry() ?: Polkurier::HOME_COUNTRY)
            ->default($address->isDefault())
            ->coverAddress($address->isCover())
            ->build();
    }

    public function mapToDeleteMessage(AddressInterface $address): DeleteMessage
    {
        return new DeleteMessage(
            id: (string)$address->getExtId(),
            type: AddressTypeEnum::tryFrom($address->getType()) ?? AddressTypeEnum::Sender,
        );
    }
}
