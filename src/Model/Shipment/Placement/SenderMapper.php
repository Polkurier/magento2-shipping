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
use Polkurier\Webservice\Builder\Order\CoverAddressMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\OrderAddressMessageBuilderFactory;
use Polkurier\Webservice\Request\Order\CoverAddressMessage;
use Polkurier\Webservice\Request\Order\OrderAddressMessage;

/**
 * Builds the sender of an order: an address book entry, the store's address or its shipping origin.
 */
readonly class SenderMapper
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private ConfigProvider $configProvider,
        private StreetParser $streetParser,
        private OrderAddressMessageBuilderFactory $orderAddressMessageBuilderFactory,
        private CoverAddressMessageBuilderFactory $coverAddressMessageBuilderFactory,
    ) {}

    /**
     * An address book entry, handing the parcels over at the point given, or at none.
     */
    public function fromAddress(AddressInterface $address, ?string $pointId): OrderAddressMessage
    {
        return $this->fromFields([
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
            AddressInterface::FIELD_POINT_ID => (string)$pointId,
        ]);
    }

    /**
     * A sender given field by field, as one typed in for a shipment; Poland unless said otherwise.
     *
     * @param array<string, string> $fields by address field
     */
    public function fromFields(array $fields): OrderAddressMessage
    {
        $value = static fn(string $field): string => \trim((string)($fields[$field] ?? ''));
        $builder = $this->orderAddressMessageBuilderFactory->create()
            ->person($value(AddressInterface::FIELD_PERSON))
            ->street($value(AddressInterface::FIELD_STREET))
            ->houseNumber($value(AddressInterface::FIELD_HOUSE_NUMBER))
            ->city($value(AddressInterface::FIELD_CITY))
            ->postcode($value(AddressInterface::FIELD_POSTCODE))
            ->country($value(AddressInterface::FIELD_COUNTRY) ?: Polkurier::HOME_COUNTRY)
            ->email($value(AddressInterface::FIELD_EMAIL))
            ->phone($value(AddressInterface::FIELD_PHONE));
        if ($value(AddressInterface::FIELD_COMPANY) !== '') {
            $builder->company($value(AddressInterface::FIELD_COMPANY));
        }
        if ($value(AddressInterface::FIELD_FLAT_NUMBER) !== '') {
            $builder->flatNumber($value(AddressInterface::FIELD_FLAT_NUMBER));
        }
        if ($value(AddressInterface::FIELD_POINT_ID) !== '') {
            $builder->pointId($value(AddressInterface::FIELD_POINT_ID));
        }

        return $builder->build();
    }

    /**
     * The store's address or shipping origin, as configured, handing the parcels over at the
     * point given, or at none; null when neither is used and filled in.
     */
    public function fromStore(int $storeId, ?string $pointId = null): ?OrderAddressMessage
    {
        $fromInformation = $this->configProvider->isUseStoreInformationAddress($storeId)
            ? $this->fromStoreInformation($storeId, $pointId)
            : null;

        $useOrigin = $this->configProvider->isUseStoreOriginAddress($storeId);

        return $fromInformation ?? ($useOrigin ? $this->fromStoreOrigin($storeId, $pointId) : null);
    }

    /**
     * The address a failed delivery goes back to instead of the sender.
     *
     * The cover address has no house number of its own in the SDK, so it goes at the end of the
     * street, which is also how the API prints it.
     */
    public function toCoverAddress(AddressInterface $address): CoverAddressMessage
    {
        $builder = $this->coverAddressMessageBuilderFactory->create()
            ->person($address->getPerson())
            ->street(\trim($address->getStreet() . ' ' . $address->getHouseNumber()))
            ->city($address->getCity())
            ->postcode($address->getPostcode())
            ->email($address->getEmail())
            ->phone($address->getPhone());
        if ($address->getCompany()) {
            $builder->company($address->getCompany());
        }
        if ($address->getFlatNumber()) {
            $builder->flatNumber($address->getFlatNumber());
        }

        return $builder->build();
    }

    /**
     * The store's address (General > Store Information), with the store's name, phone and the
     * configured store e-mail, or null when it isn't filled in.
     */
    private function fromStoreInformation(int $storeId, ?string $pointId): ?OrderAddressMessage
    {
        return $this->fromStoreAddress('general/store_information', $storeId, $pointId);
    }

    /**
     * The shipping origin (Sales > Shipping Settings > Origin), with the store's name, phone and
     * the configured store e-mail, or null when it isn't filled in.
     */
    private function fromStoreOrigin(int $storeId, ?string $pointId): ?OrderAddressMessage
    {
        return $this->fromStoreAddress('shipping/origin', $storeId, $pointId);
    }

    private function fromStoreAddress(string $prefix, int $storeId, ?string $pointId): ?OrderAddressMessage
    {
        $street = $this->streetParser->parse([
            $this->value($prefix . '/street_line1', $storeId),
            $this->value($prefix . '/street_line2', $storeId),
        ]);
        $city = $this->value($prefix . '/city', $storeId);
        $postcode = $this->value($prefix . '/postcode', $storeId);
        if ($street['street'] === '' || $city === '' || $postcode === '') {
            return null;
        }
        $name = $this->value('general/store_information/name', $storeId);
        $builder = $this->orderAddressMessageBuilderFactory->create()
            ->person($name)
            ->street($street['street'])
            ->houseNumber((string)$street['house_number'])
            ->city($city)
            ->postcode($postcode)
            ->country($this->value($prefix . '/country_id', $storeId) ?: Polkurier::HOME_COUNTRY)
            ->email($this->value(
                \sprintf('trans_email/ident_%s/email', $this->configProvider->getSenderEmailIdentity($storeId)),
                $storeId,
            ))
            ->phone($this->value('general/store_information/phone', $storeId));
        if ($name !== '') {
            $builder->company($name);
        }
        if ($street['flat_number'] !== null) {
            $builder->flatNumber($street['flat_number']);
        }
        if ($pointId !== null && $pointId !== '') {
            $builder->pointId($pointId);
        }

        return $builder->build();
    }

    private function value(string $path, int $storeId): string
    {
        return \trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
