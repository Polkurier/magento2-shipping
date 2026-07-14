<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Helper;

use Magento\Sales\Api\Data\OrderAddressInterface;
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
     * @param OrderAddressInterface $orderAddress
     *
     * @return AddressInterface
     */
    public function mapOrderAddress(OrderAddressInterface $orderAddress): AddressInterface
    {
        $address = $this->addressFactory->create();
        $address->setPerson($orderAddress->getFirstname() . ' ' . $orderAddress->getLastname());
        $address->setCompany($orderAddress->getCompany());
        $address->setStreet(\implode("\n", $orderAddress->getStreet()));
        $address->setCity($orderAddress->getCity());
        $address->setPostcode($orderAddress->getPostcode());
        $address->setCountry($orderAddress->getCountryId());
        $address->setEmail($orderAddress->getEmail());
        $address->setPhone($orderAddress->getTelephone());

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
