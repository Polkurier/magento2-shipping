<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Helper;

use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;

readonly class Formatter
{
    /**
     * @param AddressInterface $address
     *
     * @return string
     */
    public function formatAddressForSelect(AddressInterface $address): string
    {
        return \sprintf(
            '%s - %s %s/%s, %s %s',
            $address->getName(),
            $address->getStreet(),
            $address->getHouseNumber(),
            $address->getFlatNumber(),
            $address->getPostcode(),
            $address->getCity()
        );
    }

    /**
     * @param BankAccountInterface $bankAccount
     *
     * @return string
     */
    public function formatBankAccountForSelect(BankAccountInterface $bankAccount): string
    {
        return \sprintf('%s - %s', $bankAccount->getName(), $bankAccount->getNumber());
    }
}
