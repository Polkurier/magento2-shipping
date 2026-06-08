<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Quote\Api\Data\AddressInterface as QuoteAddressInterface;
use Polkurier\Webservice\Api\Data\Entity\BaseAddressInterface;
use Polkurier\Webservice\Api\Data\Entity\BaseAddressInterfaceFactory;

readonly class AddressMapper
{
    public function __construct(
        private BaseAddressInterfaceFactory $addressFactory,
    ) {}

    public function mapToWebservice(QuoteAddressInterface $address): BaseAddressInterface
    {
//        preg_match('/^(?:(\d+\w?) )?([\w\s\d\-\.]+)(?: (\d+\w?))?$/', $address->getStreet(), $matches);
//        $this->addressFactory->create([
//            'person' => $address->getFirstname() . ' ' . $address->getLastname(),
//            'company' => $address->getCompany(),
//            'street' => $address->getStreet(),
//        ]);
    }
}
