<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Quote\Api\Data\AddressInterface as QuoteAddressInterface;
use Polkurier\Shipping\Api\Data\AddressInterface as ConfigAddressInterface;
use Polkurier\Shipping\Api\Data\Order\AddressInterface;
use Polkurier\Shipping\Api\Data\Order\AddressInterfaceFactory;

readonly class AddressMapper
{
    /**
     * @param AddressInterfaceFactory $addressFactory
     */
    public function __construct(
        private AddressInterfaceFactory $addressFactory,
    ) {}

    /**
     * @param QuoteAddressInterface $quoteAddress
     *
     * @return AddressInterface
     */
    public function mapQuoteAddress(QuoteAddressInterface $quoteAddress): AddressInterface
    {
        $address = $this->addressFactory->create();
        $address->setPerson($quoteAddress->getFirstname() . ' ' . $quoteAddress->getLastname());
        $address->setCompany($quoteAddress->getCompany());
        $address->setStreet(\implode("\n", $quoteAddress->getStreet()));
        $address->setCity($quoteAddress->getCity());
        $address->setPostcode($quoteAddress->getPostcode());
        $address->setCountry($quoteAddress->getCountryId());
        $address->setEmail($quoteAddress->getEmail());
        $address->setPhone($quoteAddress->getTelephone());

        return $address;
    }

    /**
     * @param ConfigAddressInterface $configAddress
     *
     * @return AddressInterface
     */
    public function mapConfigAddress(ConfigAddressInterface $configAddress): AddressInterface
    {
        $address = $this->addressFactory->create();
        $address->setPerson($configAddress->getPerson());
        $address->setCompany($configAddress->getCompany());
        $street = $configAddress->getStreet() . ' ' . $configAddress->getHouseNumber();
        if ($configAddress->getFlatNumber()) {
            $street .= '/' . $configAddress->getFlatNumber();
        }
        $address->setStreet($street);
        $address->setCity($configAddress->getCity());
        $address->setPostcode($configAddress->getPostcode());
        $address->setCountry($configAddress->getCountry());
        $address->setEmail($configAddress->getEmail());
        $address->setPhone($configAddress->getPhone());

        return $address;
    }
}
