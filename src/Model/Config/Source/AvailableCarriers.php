<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Shipping\Model\Ext\CarrierProvider;
use Polkurier\Webservice\Api\Data\Entity\CarrierInterface;
use Polkurier\Webservice\Exception\AuthorizationException;

/**
 * Provider of options for select/multiselect form fields
 */
readonly class AvailableCarriers implements OptionSourceInterface
{
    /**
     * @param CarrierProvider $carrierProvider
     */
    public function __construct(
        /**
         * @var CarrierProvider
         */
        private CarrierProvider $carrierProvider,
    ) {}

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        try {
            return \array_map(
                fn (CarrierInterface $carrier): array => [
                    'value' => $carrier->getServicecode(),
                    'label' => $carrier->getName(),
                ],
                $this->carrierProvider->getAvailableCarriers()
            );
        } catch (AuthorizationException $e) {
            return [];
        }
    }
}
