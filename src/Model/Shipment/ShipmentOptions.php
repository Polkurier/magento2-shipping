<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Mapper\SenderMapper;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;
use Psr\Log\LoggerInterface;

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
        private readonly ErrorMessages $errorMessages,
        private readonly LoggerInterface $logger,
        private readonly SenderMapper $senderMapper,
    ) {}

    /**
     * Why the carrier list couldn't be read, once it was tried.
     */
    private ?string $failure = null;

    /**
     * @return array<string, Carrier> empty when the list is unavailable; getCarriersError() says why
     */
    public function getCarriers(): array
    {
        try {
            return $this->catalog->getCarriers();
        } catch (PolkurierException $e) {
            // The page still opens, so the merchant can see the rest; the reason goes with it.
            if ($this->failure === null) {
                $this->failure = $this->errorMessages->describe($e);
                $this->logger->warning('Polkurier: the carrier list could not be read for a shipment page', ['exception' => $e]);
            }

            return [];
        }
    }

    /**
     * Why getCarriers() came back empty, or null when the list was read.
     */
    public function getCarriersError(): ?string
    {
        $this->getCarriers();

        return $this->failure;
    }

    /**
     * @return array<string, array<string, mixed>> keyed by carrier code
     */
    public function getCarrierFeatures(): array
    {
        $features = [];
        foreach ($this->getCarriers() as $code => $carrier) {
            $features[$code] = [
                'name' => $carrier->getName(),
                'services' => \array_map(static fn($service): string => $service->value, $this->features->getServices($carrier)),
                'point' => $carrier->getAdditionalData()?->getPointsMap()?->getRecipientPoint()->value ?? PointRequirementEnum::None->value,
                'providers' => $this->features->getPointProviders($carrier),
                // Whether the parcels are handed over at a point: NONE, OPTIONAL or REQUIRED.
                'sender_point' => $this->features->getSenderPointRequirement($carrier)->value,
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
    public function getCarrierOptions(): array
    {
        $options = [];
        foreach ($this->getCarriers() as $code => $carrier) {
            $options[] = ['value' => (string)$code, 'label' => $carrier->getName() !== '' ? $carrier->getName() : (string)$code];
        }

        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getSenderOptions(bool $coverOnly = false): array
    {
        $options = [];
        foreach ($this->masterData->getSenderAddresses($coverOnly) as $address) {
            $options[] = ['value' => (string)$address->getId(), 'label' => $this->addressLabel($address)];
        }

        return $options;
    }

    /**
     * What a shipment can be sent from: the store's address when there is one to send from, the
     * addresses, and one typed in for the shipment.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getSenderChoices(int $storeId): array
    {
        return \array_merge(
            $this->senderMapper->fromStore($storeId) !== null
                ? [['value' => '', 'label' => (string)\__('The store\'s address')]]
                : [],
            $this->getSenderOptions(),
            [['value' => ShipmentDraftInterface::NEW, 'label' => (string)\__('A new address')]],
        );
    }

    /**
     * What cash on delivery can be paid out to: the bank accounts, and one typed in.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getBankAccountChoices(): array
    {
        return \array_merge(
            $this->getBankAccountOptions(),
            [['value' => ShipmentDraftInterface::NEW, 'label' => (string)\__('A new bank account')]],
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getBankAccountOptions(): array
    {
        $options = [];
        foreach ($this->masterData->getBankAccounts() as $account) {
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
