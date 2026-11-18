<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Webservice\Builder\Entity\Order\AddressBuilderFactory;
use Polkurier\Webservice\Entity\Order\Address;

/**
 * Builds the sender of an order: an address book entry, the store's address or its shipping origin.
 */
readonly class SenderMapper
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private ConfigProvider $configProvider,
        private StreetParser $streetParser,
        private AddressBuilderFactory $addressBuilderFactory,
    ) {}

    /**
     * An address book entry, handing the parcels over at the point given, or at none.
     */
    public function mapFromAddress(AddressInterface $address, ?string $pointId): Address
    {
        return $this->mapFromFields([
            AddressInterface::FIELD_PERSON => $address->getPerson(),
            AddressInterface::FIELD_COMPANY => (string)$address->getCompany(),
            AddressInterface::FIELD_STREET => $address->getStreet(),
            AddressInterface::FIELD_HOUSE_NUMBER => (string)$address->getHouseNumber(),
            AddressInterface::FIELD_FLAT_NUMBER => (string)$address->getFlatNumber(),
            AddressInterface::FIELD_CITY => $address->getCity(),
            AddressInterface::FIELD_POSTCODE => $address->getPostcode(),
            AddressInterface::FIELD_COUNTRY => $address->getCountry(),
            AddressInterface::FIELD_EMAIL => $address->getEmail(),
            AddressInterface::FIELD_PHONE => $address->getPhone(),
        ], $pointId);
    }

    /**
     * A sender given field by field, as one typed in for a shipment, handing the parcels over at the
     * point given, or at none; Poland unless said otherwise.
     *
     * @param array<string, string> $fields by address field
     */
    public function mapFromFields(array $fields, ?string $pointId = null): Address
    {
        $value = static fn(string $field): string => \trim((string)($fields[$field] ?? ''));
        $builder = $this->addressBuilderFactory->create()
            ->setPerson($value(AddressInterface::FIELD_PERSON))
            ->setStreet($value(AddressInterface::FIELD_STREET))
            ->setHouseNumber($value(AddressInterface::FIELD_HOUSE_NUMBER))
            ->setCity($value(AddressInterface::FIELD_CITY))
            ->setPostcode($value(AddressInterface::FIELD_POSTCODE))
            ->setCountry($value(AddressInterface::FIELD_COUNTRY) ?: Polkurier::HOME_COUNTRY)
            ->setEmail($value(AddressInterface::FIELD_EMAIL))
            ->setPhone($value(AddressInterface::FIELD_PHONE));
        if ($value(AddressInterface::FIELD_COMPANY) !== '') {
            $builder->setCompany($value(AddressInterface::FIELD_COMPANY));
        }
        if ($value(AddressInterface::FIELD_FLAT_NUMBER) !== '') {
            $builder->setFlatNumber($value(AddressInterface::FIELD_FLAT_NUMBER));
        }
        if ($pointId !== null && $pointId !== '') {
            $builder->setPointId($pointId);
        }

        return $builder->build();
    }

    /**
     * The store's address or shipping origin, as configured, handing the parcels over at the
     * point given, or at none; null when neither is used and filled in.
     */
    public function mapFromStore(int $storeId, ?string $pointId = null): ?Address
    {
        $fromInformation = $this->configProvider->shouldUseStoreInformationAddress($storeId)
            ? $this->mapFromStoreInformation($storeId, $pointId)
            : null;

        $useOrigin = $this->configProvider->shouldUseStoreOriginAddress($storeId);

        return $fromInformation ?? ($useOrigin ? $this->mapFromStoreOrigin($storeId, $pointId) : null);
    }

    /**
     * The store's address (General > Store Information), with the store's name, phone and the
     * configured store e-mail, or null when it isn't filled in.
     */
    private function mapFromStoreInformation(int $storeId, ?string $pointId): ?Address
    {
        return $this->mapFromStoreAddress('general/store_information', $storeId, $pointId);
    }

    /**
     * The shipping origin (Sales > Shipping Settings > Origin), with the store's name, phone and
     * the configured store e-mail, or null when it isn't filled in.
     */
    private function mapFromStoreOrigin(int $storeId, ?string $pointId): ?Address
    {
        return $this->mapFromStoreAddress('shipping/origin', $storeId, $pointId);
    }

    private function mapFromStoreAddress(string $prefix, int $storeId, ?string $pointId): ?Address
    {
        $street = $this->streetParser->parse([
            $this->getConfigValue($prefix . '/street_line1', $storeId),
            $this->getConfigValue($prefix . '/street_line2', $storeId),
        ]);
        $city = $this->getConfigValue($prefix . '/city', $storeId);
        $postcode = $this->getConfigValue($prefix . '/postcode', $storeId);
        if ($street['street'] === '' || $city === '' || $postcode === '') {
            return null;
        }
        $name = $this->getConfigValue('general/store_information/name', $storeId);
        $builder = $this->addressBuilderFactory->create()
            ->setPerson($name)
            ->setStreet($street['street'])
            ->setHouseNumber((string)$street['house_number'])
            ->setCity($city)
            ->setPostcode($postcode)
            ->setCountry($this->getConfigValue($prefix . '/country_id', $storeId) ?: Polkurier::HOME_COUNTRY)
            ->setEmail($this->getConfigValue(
                \sprintf('trans_email/ident_%s/email', $this->configProvider->getSenderEmailIdentity($storeId)),
                $storeId,
            ))
            ->setPhone($this->getConfigValue('general/store_information/phone', $storeId));
        if ($name !== '') {
            $builder->setCompany($name);
        }
        if ($street['flat_number'] !== null) {
            $builder->setFlatNumber($street['flat_number']);
        }
        if ($pointId !== null && $pointId !== '') {
            $builder->setPointId($pointId);
        }

        return $builder->build();
    }

    private function getConfigValue(string $path, int $storeId): string
    {
        return \trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
