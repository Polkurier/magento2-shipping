<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Api;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order\Address;
use Polkurier\Shipping\Api\Order\AddressRepositoryInterface;
use Polkurier\Shipping\Helper\AddressMapper;
use Polkurier\Shipping\Helper\ShipmentHelper;

readonly class OrderAddressRepositoryPlugin
{
    /**
     * @param AddressRepositoryInterface $addressRepository
     * @param ShipmentHelper $shipmentHelper
     * @param AddressMapper $addressMapper
     */
    public function __construct(
        /**
         * @var AddressRepositoryInterface
         */
        private AddressRepositoryInterface $addressRepository,

        /**
         * @var ShipmentHelper
         */
        private ShipmentHelper $shipmentHelper,

        /**
         * @var AddressMapper
         */
        private AddressMapper $addressMapper,
    ) {}

    /**
     * @param OrderAddressRepositoryInterface $subject
     * @param OrderAddressInterface $parentAddress
     *
     * @return OrderAddressInterface
     */
    public function afterSave(
        OrderAddressRepositoryInterface $subject,
        OrderAddressInterface $parentAddress
    ): OrderAddressInterface {
        if ($parentAddress->getAddressType() === Address::TYPE_SHIPPING
            && $this->shipmentHelper->isOrderAddressPolkurierShipment($parentAddress)
            && $origAddress = $this->addressRepository->findOneByParentId((int)$parentAddress->getId())
        ) {
            $address = $this->addressMapper->mapOrderAddress($parentAddress);
            $address->setOrderId((int)$parentAddress->getOrder()->getId());
            $address->setParentAddressId((int)$parentAddress->getId());
            $address->setPointId($origAddress?->getPointId());
            $this->addressRepository->save($address);
        }

        return $parentAddress;
    }
}
