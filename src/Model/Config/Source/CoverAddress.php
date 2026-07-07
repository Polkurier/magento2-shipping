<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Helper\Formatter;

readonly class CoverAddress implements OptionSourceInterface
{
    /**
     * @param AddressRepositoryInterface $addressRepository
     * @param Formatter $formatter
     */
    public function __construct(
        /**
         * @var AddressRepositoryInterface
         */
        private AddressRepositoryInterface $addressRepository,

        /**
         * @var Formatter
         */
        private Formatter $formatter,
    ) {}

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return \array_map(
            fn (AddressInterface $address) => [
                'value' => $address->getAddressId(),
                'label' => $this->formatter->formatAddressForSelect($address),
            ],
            $this->addressRepository->findCover()->getItems()
        );
    }
}
