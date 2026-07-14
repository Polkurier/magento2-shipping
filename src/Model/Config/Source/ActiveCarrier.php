<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Webservice\Exception\AuthorizationException;

readonly class ActiveCarrier implements OptionSourceInterface
{
    /**
     * @param CarrierConfigRepositoryInterface $carrierConfigRepository
     */
    public function __construct(
        /**
         * @var CarrierConfigRepositoryInterface
         */
        private CarrierConfigRepositoryInterface $carrierConfigRepository,
    ) {}

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        try {
            return \array_map(
                fn (CarrierConfigInterface $carrierConfig): array => [
                    'value' => $carrierConfig->getCarrierCode(),
                    'label' => $carrierConfig->getCustomLabel() ?: $carrierConfig->getCarrierName(),
                ],
                $this->carrierConfigRepository->findActive()->getItems()
            );
        } catch (AuthorizationException $e) {
            return [];
        }
    }
}
