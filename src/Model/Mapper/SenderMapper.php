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
class SenderMapper
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config,
        private readonly StreetParser $streetParser,
    ) {}

    public function fromAddress(AddressInterface $address): OrderAddressMessage
    {
        $builder = OrderAddressMessageBuilder::create()
            ->person($address->getPerson())
            ->street($address->getStreet())
            ->houseNumber($address->getHouseNumber())
            ->city($address->getCity())
            ->postcode($address->getPostcode())
            ->country($address->getCountry() ?: 'PL')
            ->email($address->getEmail())
            ->phone($address->getPhone());
        if ($address->getCompany()) {
            $builder->company($address->getCompany());
        }
        if ($address->getFlatNumber()) {
            $builder->flatNumber($address->getFlatNumber());
        }
        if ($address->getPointId()) {
            $builder->pointId($address->getPointId());
        }

        return $builder->build();
    }

    /**
     * The sender when none is chosen from the address book: the store's address, then its shipping
     * origin, each only when the Shipments settings allow it. Null when neither applies.
     */
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
