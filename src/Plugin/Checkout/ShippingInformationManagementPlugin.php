<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\CartRepositoryInterface;
use Polkurier\Shipping\Model\PickupPoint\PointSelectionRecorder;

/**
 * Takes the pickup point chosen in checkout along with the shipping method.
 */
readonly class ShippingInformationManagementPlugin
{
    public function __construct(
        private CartRepositoryInterface $quoteRepository,
        private PointSelectionRecorder $pointSelectionRecorder,
    ) {}

    /**
     * @return array{0: int, 1: ShippingInformationInterface}
     * @throws InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        mixed $cartId,
        ShippingInformationInterface $addressInformation,
    ): array {
        $this->pointSelectionRecorder->select(
            $this->quoteRepository->getActive((int)$cartId),
            (string)$addressInformation->getShippingCarrierCode(),
            (string)$addressInformation->getShippingMethodCode(),
            $addressInformation->getExtensionAttributes()?->getPickupPointId(),
        );

        return [(int)$cartId, $addressInformation];
    }
}
