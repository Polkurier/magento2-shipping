<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Screen;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\PickupPoint\DefaultSenderPointManager;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Shipment\Placement\SenderMapper;
use Polkurier\Webservice\Dto\Response\Carrier;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The choices a shipment is put together from, for the admin pages that edit shipment options:
 * the account's carriers and what each supports, sender addresses and bank accounts.
 */
class ShipmentOptionsProvider implements ResetAfterRequestInterface
{
    /**
     * Why the carrier list couldn't be read, once it was tried.
     */
    private ?string $failure = null;

    public function __construct(
        private readonly CarrierCatalog $carrierCatalog,
        private readonly CarrierFeatureResolver $carrierFeatureResolver,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly BankAccountRepositoryInterface $bankAccountRepository,
        private readonly ErrorMessageResolver $errorMessageResolver,
        private readonly Logger $logger,
        private readonly SenderMapper $senderMapper,
        private readonly DefaultSenderPointManager $defaultSenderPointManager,
    ) {}

    /**
     * @return array<string, Carrier> empty when the list is unavailable; getCarriersError() says why
     */
    public function getCarriers(): array
    {
        try {
            return $this->carrierCatalog->getCarriers();
        } catch (PolkurierException $e) {
            // The page still opens, so the merchant can see the rest; the reason goes with it.
            if ($this->failure === null) {
                $this->failure = (string)$this->errorMessageResolver->createPhrase($e);
                $this->logger->warning(
                    'Polkurier: the carrier list could not be read for a shipment page',
                    ['exception' => $e],
                );
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
                'name' => $carrier->name,
                'services' => \array_map(
                    static fn($service): string => $service->value,
                    $this->carrierFeatureResolver->getServices($carrier),
                ),
                'point' => $carrier->additionalData?->pointsMap?->recipientPoint->value
                    ?? PointRequirementEnum::None->value,
                'providers' => $this->carrierFeatureResolver->getPointProviders($carrier),
                // Whether the parcels are handed over at a point: NONE, OPTIONAL or REQUIRED.
                'sender_point' => $this->carrierFeatureResolver->getSenderPointRequirement($carrier)->value,
                // The account's drop-off point for a carrier that takes parcels at points, '' without one.
                'default_sender_point' => (string)$this->defaultSenderPointManager->getForCarrier($carrier),
                'cod' => $this->carrierFeatureResolver->isCodAvailable($carrier),
                'insurance' => $this->carrierFeatureResolver->isInsuranceAvailable($carrier),
                'insurance_with_cod' => $this->carrierFeatureResolver->isInsuranceRequiredWithCod($carrier),
                'drop_off' => $this->carrierFeatureResolver->isDropOffAvailable($carrier),
                'pickup_date' => $this->carrierFeatureResolver->isPickupDateSupported($carrier),
                'pickup_date_required' => $this->carrierFeatureResolver->isPickupDateRequired($carrier),
                'pickup_time_required' => $this->carrierFeatureResolver->isPickupTimeRequired($carrier),
                'shipment_types' => \array_map(
                    static fn($type): string => $type->value,
                    $this->carrierFeatureResolver->getShipmentTypes($carrier),
                ),
                'fields' => \array_map(static fn($field): array => [
                    'name' => $field->name,
                    'label' => $field->label,
                    'description' => $field->description,
                    'required' => $field->required,
                ], $this->carrierFeatureResolver->getAdditionalFields($carrier)),
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
            $options[] = [
                'value' => (string)$code,
                'label' => $carrier->name !== '' ? $carrier->name : (string)$code,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getSenderOptions(bool $coverOnly = false): array
    {
        $options = [];
        foreach ($this->addressRepository->getSenderAddresses($coverOnly) as $address) {
            $options[] = ['value' => (string)$address->getId(), 'label' => $this->getAddressLabel($address)];
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
            $this->senderMapper->mapFromStore($storeId) !== null
                ? [['value' => '', 'label' => (string)__('The store\'s address')]]
                : [],
            $this->getSenderOptions(),
            [['value' => ShipmentDraftInterface::NEW, 'label' => (string)__('A new address')]],
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
            [['value' => ShipmentDraftInterface::NEW, 'label' => (string)__('A new bank account')]],
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getBankAccountOptions(): array
    {
        $options = [];
        foreach ($this->bankAccountRepository->getAvailable() as $account) {
            $options[] = [
                'value' => (string)$account->getId(),
                'label' => $account->getName() . ' — ' . $account->getNumber(),
            ];
        }

        return $options;
    }

    public function getAddressLabel(AddressInterface $address): string
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

    public function _resetState(): void
    {
        $this->failure = null;
    }
}
