<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Mapper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Webservice\Builder\Order\CoverAddressMessageBuilder;
use Polkurier\Webservice\Builder\Order\OrderAddressMessageBuilder;
use Polkurier\Webservice\Request\Order\CoverAddressMessage;
use Polkurier\Webservice\Request\Order\OrderAddressMessage;

/**
 * Builds the sender of an order: an address book entry, the store's address or its shipping origin.
 */
readonly class SenderMapper
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private Config $config,
        private StreetParser $streetParser,
    ) {}

    public function fromAddress(AddressInterface $address): OrderAddressMessage
    {
        return $this->fromFields([
            AddressInterface::PERSON => $address->getPerson(),
            AddressInterface::COMPANY => (string)$address->getCompany(),
            AddressInterface::STREET => $address->getStreet(),
            AddressInterface::HOUSE_NUMBER => (string)$address->getHouseNumber(),
            AddressInterface::FLAT_NUMBER => (string)$address->getFlatNumber(),
            AddressInterface::CITY => $address->getCity(),
            AddressInterface::POSTCODE => $address->getPostcode(),
            AddressInterface::COUNTRY => $address->getCountry(),
            AddressInterface::EMAIL => $address->getEmail(),
            AddressInterface::PHONE => $address->getPhone(),
            AddressInterface::POINT_ID => (string)$address->getPointId(),
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
        $builder = OrderAddressMessageBuilder::create()
            ->person($value(AddressInterface::PERSON))
            ->street($value(AddressInterface::STREET))
            ->houseNumber($value(AddressInterface::HOUSE_NUMBER))
            ->city($value(AddressInterface::CITY))
            ->postcode($value(AddressInterface::POSTCODE))
            ->country($value(AddressInterface::COUNTRY) ?: 'PL')
            ->email($value(AddressInterface::EMAIL))
            ->phone($value(AddressInterface::PHONE));
        if ($value(AddressInterface::COMPANY) !== '') {
            $builder->company($value(AddressInterface::COMPANY));
        }
        if ($value(AddressInterface::FLAT_NUMBER) !== '') {
            $builder->flatNumber($value(AddressInterface::FLAT_NUMBER));
        }
        if ($value(AddressInterface::POINT_ID) !== '') {
            $builder->pointId($value(AddressInterface::POINT_ID));
        }

        return $builder->build();
    }

    /**
     * The sender handing the parcels over at the point given, or at none.
     */
    public function withPoint(OrderAddressMessage $sender, ?string $pointId): OrderAddressMessage
    {
        return $this->fromFields([
            AddressInterface::PERSON => $sender->getPerson(),
            AddressInterface::COMPANY => (string)$sender->getCompany(),
            AddressInterface::STREET => $sender->getStreet(),
            AddressInterface::HOUSE_NUMBER => (string)$sender->getHouseNumber(),
            AddressInterface::FLAT_NUMBER => (string)$sender->getFlatNumber(),
            AddressInterface::CITY => $sender->getCity(),
            AddressInterface::POSTCODE => $sender->getPostcode(),
            AddressInterface::COUNTRY => $sender->getCountry(),
            AddressInterface::EMAIL => $sender->getEmail(),
            AddressInterface::PHONE => $sender->getPhone(),
            AddressInterface::POINT_ID => (string)$pointId,
        ]);
    }

    public function fromStore(int $storeId): ?OrderAddressMessage
    {
        return ($this->config->isUseStoreInformationAddress($storeId) ? $this->fromStoreInformation($storeId) : null)
            ?? ($this->config->isUseStoreOriginAddress($storeId) ? $this->fromStoreOrigin($storeId) : null);
    }

    /**
     * The store's address (General > Store Information), with the store's name, phone and the
     * configured store e-mail, or null when it isn't filled in.
     */
    public function fromStoreInformation(int $storeId): ?OrderAddressMessage
    {
        return $this->fromStoreAddress('general/store_information', $storeId);
    }

    /**
     * The shipping origin (Sales > Shipping Settings > Origin), with the store's name, phone and
     * the configured store e-mail, or null when it isn't filled in.
     */
    public function fromStoreOrigin(int $storeId): ?OrderAddressMessage
    {
        return $this->fromStoreAddress('shipping/origin', $storeId);
    }

    private function fromStoreAddress(string $prefix, int $storeId): ?OrderAddressMessage
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
        $builder = OrderAddressMessageBuilder::create()
            ->person($name)
            ->company($name)
            ->street($street['street'])
            ->houseNumber((string)$street['house_number'])
            ->city($city)
            ->postcode($postcode)
            ->country($this->value($prefix . '/country_id', $storeId) ?: 'PL')
            ->email($this->value(\sprintf('trans_email/ident_%s/email', $this->config->getSenderEmailIdentity($storeId)), $storeId))
            ->phone($this->value('general/store_information/phone', $storeId));
        if ($street['flat_number'] !== null) {
            $builder->flatNumber($street['flat_number']);
        }

        return $builder->build();
    }

    /**
     * The address a failed delivery goes back to instead of the sender.
     *
     * The cover address has no house number of its own in the SDK, so it goes at the end of the
     * street, which is also how the API prints it.
     */
    public function toCoverAddress(AddressInterface $address): CoverAddressMessage
    {
        $builder = CoverAddressMessageBuilder::create()
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

    private function value(string $path, int $storeId): string
    {
        return \trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
