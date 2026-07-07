<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Provider\ConfigProvider;
use Polkurier\Shipping\Model\Provider\Ext\MapTokenProvider;
use Polkurier\Webservice\Enum\PointFunctionEnum;
use Polkurier\Webservice\Enum\PointRequirementEnum;
use Polkurier\Webservice\Model\Entity\Carrier;
use Polkurier\Webservice\Model\Entity\Carrier\AdditionalData;
use Polkurier\Webservice\Model\Entity\Carrier\AdditionalData\PointsMap;

readonly class MapConfigProvider implements ConfigProviderInterface
{
    /**
     * @param ConfigProvider $configProvider
     * @param MapTokenProvider $mapTokenProvider
     * @param CarrierConfigRepositoryInterface $carrierConfigRepository
     */
    public function __construct(
        private ConfigProvider $configProvider,
        private MapTokenProvider $mapTokenProvider,
        private CarrierConfigRepositoryInterface $carrierConfigRepository,
    ) {}

    /**
     * @return array
     */
    public function getConfig(): array
    {
        $allowedMethods = $this->getAllowedMethods();
        $methodProviders = [];
        foreach ($allowedMethods as $carrier) {
            $methodProviders[$carrier->getCarrierCode()] =
                $carrier->getExtData()[Carrier::FIELD_ADDITIONAL_DATA][AdditionalData::FIELD_POINTS_MAP][PointsMap::FIELD_AVAILABLE_PROVIDERS];
        }

        return ['polkurier' => ['map' => [
            'token' => $this->mapTokenProvider->getToken(),
            'baseUrl' => $this->configProvider->getMapBaseUrl() . '/',
            'allowedMethods' => \array_map(
                fn (CarrierConfigInterface $carrier) => $carrier->getCarrierCode(),
                $allowedMethods
            ),
            'methodProviders' => $methodProviders,
            'defaultLatitude' => $this->configProvider->getMapDefaultLatitude(),
            'defaultLongitude' => $this->configProvider->getMapDefaultLongitude(),
            'defaultZoom' => $this->configProvider->getMapDefaultZoom(),
            'showList' => $this->configProvider->isMapShowList(),
            'showSearchInput' => $this->configProvider->isMapShowSearchInput(),
            'requiredPointFunctions' => \array_map(
                fn (PointFunctionEnum $pointFunction) => $pointFunction->value,
                $this->configProvider->getRequiredPointFunctions()
            ),
        ]]];
    }

    /**
     * @return CarrierConfigInterface[]
     */
    private function getAllowedMethods(): array
    {
        return \array_values(\array_filter(
            $this->carrierConfigRepository->findActive()->getItems(),
            fn (CarrierConfigInterface $carrier) => $this->isMethodAllowed($carrier)
        ));
    }

    /**
     * @param CarrierConfigInterface $carrierConfig
     *
     * @return bool
     */
    private function isMethodAllowed(CarrierConfigInterface $carrierConfig): bool
    {
        $pointsMap = $carrierConfig->getExtData()[Carrier::FIELD_ADDITIONAL_DATA][AdditionalData::FIELD_POINTS_MAP] ?? null;

        return $pointsMap !== null
            && $pointsMap[PointsMap::FIELD_RECIPIENT_POINT] === PointRequirementEnum::REQUIRED->value;
    }
}
