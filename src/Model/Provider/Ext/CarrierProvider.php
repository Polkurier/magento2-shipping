<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider\Ext;

use Polkurier\Webservice\Api\CourierServiceInterface;
use Polkurier\Webservice\Api\Data\Entity\CarrierInterface;
use Polkurier\Webservice\Api\Data\Message\Courier\AvailableCarriersMessageInterfaceFactory;

class CarrierProvider
{
    /**
     * @var CarrierInterface
     */
    private array $availableCarriers = [];

    /**
     * @param CourierServiceInterface $courierService
     * @param AvailableCarriersMessageInterfaceFactory $availableCarriersMessageFactory
     */
    public function __construct(
        /**
         * @var CourierServiceInterface
         */
        private readonly CourierServiceInterface $courierService,

        /**
         * @var AvailableCarriersMessageInterfaceFactory
         */
        private readonly AvailableCarriersMessageInterfaceFactory $availableCarriersMessageFactory,
    ) {}

    /**
     * @param bool $additionalData
     *
     * @return array<string, CarrierInterface>
     */
    public function getAvailableCarriers(bool $additionalData = false): array
    {
        if (isset($this->availableCarriers[(int)$additionalData])) {
            return $this->availableCarriers[(int)$additionalData];
        }

        $message = $this->availableCarriersMessageFactory->create(['additionalData' => $additionalData]);
        $result = $this->courierService->availableCarriers($message)
            ->getParsedResponse()->getResponse();
        /** @var CarrierInterface $carrier */
        foreach ($result as $carrier) {
            $this->availableCarriers[(int)$additionalData][$carrier->getServiceCode()] = $carrier;
        }

        return $this->availableCarriers[(int)$additionalData];
    }

    /**
     * TODO: handle invalid carrier code
     *
     * @param string $carrierCode
     * @param bool $additionalData
     *
     * @return CarrierInterface|null
     */
    public function getCarrier(string $carrierCode, bool $additionalData = false): ?CarrierInterface
    {
        if (isset($this->availableCarriers[(int)$additionalData][$carrierCode])) {
            return $this->availableCarriers[(int)$additionalData][$carrierCode];
        }

        $message = $this->availableCarriersMessageFactory->create([
            'returnCarrier' => $carrierCode,
            'additionalData' => $additionalData,
        ]);
        $result = $this->courierService->availableCarriers($message)
            ->getParsedResponse()->getResponse();
        /** @var CarrierInterface $carrier */
        foreach ($result as $carrier) {
            $this->availableCarriers[(int)$additionalData][$carrier->getServiceCode()] = $carrier;
        }

        return $this->availableCarriers[(int)$additionalData][$carrierCode];
    }
}
