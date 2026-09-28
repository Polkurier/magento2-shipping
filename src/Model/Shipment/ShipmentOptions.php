<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;

/**
 * The choices a shipment is put together from, for the admin pages that edit shipment options:
 * the account's carriers and what each supports, sender addresses and bank accounts.
 */
class ShipmentOptions
{
    public function __construct(
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        private readonly MasterData $masterData,
    ) {}

    /**
     * @return array<string, Carrier> empty when the list is unavailable
     */
    public function getCarriers(int $storeId): array
    {
        try {
            return $this->catalog->getCarriers($storeId);
        } catch (PolkurierException) {
            return [];
        }
    }

    /**
     * @return array<string, array<string, mixed>> keyed by carrier code
     */
    public function getCarrierFeatures(int $storeId): array
    {
        $features = [];
        foreach ($this->getCarriers($storeId) as $code => $carrier) {
            $features[$code] = [
                'name' => $carrier->getName(),
                'services' => \array_map(static fn($service): string => $service->value, $this->features->getServices($carrier)),
                'point' => $carrier->getAdditionalData()?->getPointsMap()?->getRecipientPoint()->value ?? PointRequirementEnum::None->value,
                'providers' => $this->features->getPointProviders($carrier),
                'cod' => $this->features->isCodAvailable($carrier),
                'insurance' => $this->features->isInsuranceAvailable($carrier),
                'insurance_with_cod' => $this->features->isInsuranceRequiredWithCod($carrier),
                'drop_off' => $this->features->isDropOffAvailable($carrier),
                'pickup_date' => $this->features->isPickupDateSupported($carrier),
                'pickup_date_required' => $this->features->isPickupDateRequired($carrier),
                'pickup_time_required' => $this->features->isPickupTimeRequired($carrier),
                'shipment_types' => \array_map(static fn($type): string => $type->value, $this->features->getShipmentTypes($carrier)),
                'fields' => \array_map(static fn($field): array => [
                    'name' => $field->getName(),
                    'label' => $field->getLabel(),
                    'description' => $field->getDescription(),
                    'required' => $field->isRequired(),
                ], $this->features->getAdditionalFields($carrier)),
            ];
        }

        return $features;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getCarrierOptions(int $storeId): array
    {
        $options = [];
        foreach ($this->getCarriers($storeId) as $code => $carrier) {
            $options[] = ['value' => (string)$code, 'label' => \sprintf('%s (%s)', $carrier->getName(), $code)];
        }

        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getSenderOptions(int $websiteId, bool $coverOnly = false): array
    {
        $options = [];
        foreach ($this->masterData->getSenderAddresses($websiteId, $coverOnly) as $address) {
            $options[] = ['value' => (string)$address->getId(), 'label' => $this->addressLabel($address)];
        }

        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getBankAccountOptions(int $websiteId): array
    {
        $options = [];
        foreach ($this->masterData->getBankAccounts($websiteId) as $account) {
            $options[] = ['value' => (string)$account->getId(), 'label' => $account->getName() . ' — ' . $account->getNumber()];
        }

        return $options;
    }

    public function addressLabel(AddressInterface $address): string
    {
        return \sprintf(
            '%s — %s %s%s, %s %s',
            $address->getName(),
            $address->getStreet(),
            $address->getHouseNumber(),
            $address->getFlatNumber() ? '/' . $address->getFlatNumber() : '',
            $address->getPostcode(),
            $address->getCity(),
        );
    }
}
