<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\BankAccount\BankAccountMapper;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sdk\PolkurierTimeZoneProvider;
use Polkurier\Webservice\Builder\Entity\Order\AddressBuilderFactory;
use Polkurier\Webservice\Builder\Entity\Order\CodBuilderFactory;
use Polkurier\Webservice\Builder\Entity\Order\PackItemBuilderFactory;
use Polkurier\Webservice\Builder\Entity\Order\PickupBuilderFactory;
use Polkurier\Webservice\Builder\Entity\OrderBuilderFactory;
use Polkurier\Webservice\Dto\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Entity\Order\Address;
use Polkurier\Webservice\Entity\Order\Cod;
use Polkurier\Webservice\Entity\Order\PackItem;
use Polkurier\Webservice\Entity\Order\Pickup;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\CodReturnTypeEnum;
use Polkurier\Webservice\Enum\Order\CodTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

/**
 * Builds the create_order payload from a shipment draft.
 *
 * Only shapes the payload; the SDK validates it when it is sent, and the API decides.
 */
readonly class CreateOrderMessageMapper
{
    public function __construct(
        private SenderMapper $senderMapper,
        private AddressRepositoryInterface $addressRepository,
        private BankAccountRepositoryInterface $bankAccountRepository,
        private ConfigProvider $configProvider,
        private OrderBuilderFactory $orderBuilderFactory,
        private PackItemBuilderFactory $packItemBuilderFactory,
        private AddressBuilderFactory $addressBuilderFactory,
        private CodBuilderFactory $codBuilderFactory,
        private PickupBuilderFactory $pickupBuilderFactory,
    ) {}

    /**
     * @param array{application: string, id: string, number: string}|null $externalIds what
     *        identifies the order as the shop's, see
     *        {@see \Polkurier\Shipping\Model\Shipment\Placement\ExternalIdService}
     *
     * @throws LocalizedException when the draft refers to something that does not exist, or its
     *     parcels are of more than one kind
     */
    public function map(ShipmentDraftInterface $draft, int $storeId, ?array $externalIds = null): CreateOrderMessage
    {
        $types = ParcelTypeMapper::mapShipmentTypes(\array_map(
            static fn(PackInterface $pack): string => $pack->getPackType(),
            $draft->getPacks(),
        ));
        if (\count($types) > 1) {
            throw new LocalizedException(__(
                'Parcels, envelopes and pallets can\'t go in one shipment: keep to one of them.',
            ));
        }
        $builder = $this->orderBuilderFactory->create()
            ->setCourier($draft->getCarrierCode())
            ->setShipmentType(
                $types[0] ?? ShipmentTypeEnum::tryFrom($draft->getShipmentType()) ?? ShipmentTypeEnum::Box,
            )
            ->setPacks(\array_map($this->mapPack(...), $draft->getPacks()))
            ->setSender($this->mapSender($draft, $storeId))
            ->setRecipient($this->mapRecipient($draft))
            // The API requires a description although the SDK does not. Its limit counts bytes, so
            // the cut does too, without splitting a letter.
            ->setDescription(
                \mb_strcut((string)$draft->getDescription(), 0, Order::DESCRIPTION_MAX_LENGTH),
            );

        if ((float)$draft->getCodAmount() > 0) {
            $builder->setCod($this->mapCod($draft, $storeId));
        }
        if ((float)$draft->getInsuranceAmount() > 0) {
            $builder->setInsurance(\round((float)$draft->getInsuranceAmount(), 2));
        }
        if ($draft->getCoverAddressId()) {
            $builder->setCoverAddress(
                $this->senderMapper->mapFromAddress($this->loadAddress($draft->getCoverAddressId()), null),
            );
        }
        $pickup = $this->mapPickup($draft);
        if ($pickup !== null) {
            $builder->setPickup($pickup);
        }
        if (!$draft->getCoverAddressId()
            && \in_array(AdditionalServiceEnum::CoverSenderAddress->value, $draft->getServices(), true)
        ) {
            throw new LocalizedException(__('Choose the address to print on the label as the sender.'));
        }
        $services = $this->mapServices($draft->getServices());
        if ($services !== []) {
            $builder->setCourierService($services);
        }
        if ($externalIds !== null) {
            $builder->setExternalApplicationId($externalIds['application'])
                ->setExternalOrderId($externalIds['id'])
                ->setExternalOrderNumber($externalIds['number']);
        }
        $fields = \array_filter(
            \array_map('strval', $draft->getAdditionalFields()),
            static fn(string $value): bool => $value !== '',
        );
        if ($fields !== []) {
            $builder->setAdditionalFields($fields);
        }

        return new CreateOrderMessage($builder->build());
    }

    /**
     * Each named SMS notification replaces its plain variant rather than adding to it, so only the
     * named one is sent when both were chosen. The cover sender address isn't sent: the cover
     * address switches it on, and Polkurier refuses it without one.
     *
     * @param string[] $codes
     *
     * @return AdditionalServiceEnum[]
     */
    public function mapServices(array $codes): array
    {
        $services = \array_values(\array_unique(
            \array_filter(
                \array_map(
                    static fn(string $service): ?AdditionalServiceEnum => AdditionalServiceEnum::tryFrom($service),
                    $codes,
                ),
                static fn(?AdditionalServiceEnum $service): bool => $service !== null
                    && $service !== AdditionalServiceEnum::CoverSenderAddress,
            ),
            \SORT_REGULAR,
        ));
        $replaced = [
            AdditionalServiceEnum::SmsOnDispatchWithSenderName->value => AdditionalServiceEnum::SmsOnDispatch,
            AdditionalServiceEnum::SmsOutForDeliveryWithSenderName->value => AdditionalServiceEnum::SmsOutForDelivery,
        ];
        foreach ($services as $service) {
            if (isset($replaced[$service->value])) {
                $plain = $replaced[$service->value];
                $services = \array_filter(
                    $services,
                    static fn(AdditionalServiceEnum $candidate): bool => $candidate !== $plain,
                );
            }
        }

        return \array_values($services);
    }

    public function mapPack(PackInterface $pack): PackItem
    {
        return $this->packItemBuilderFactory->create()
            ->setLength($pack->getLength())
            ->setWidth($pack->getWidth())
            ->setHeight($pack->getHeight())
            ->setWeight(\round($pack->getWeight(), 3))
            ->setType(ParcelTypeMapper::mapPolkurierType($pack->getPackType()))
            ->setAmount(\max(1, $pack->getAmount()))
            ->build();
    }

    public function mapRecipient(ShipmentDraftInterface $draft): Address
    {
        $builder = $this->addressBuilderFactory->create()
            ->setPerson($draft->getRecipientPerson())
            ->setStreet($draft->getRecipientStreet())
            ->setCity($draft->getRecipientCity())
            ->setPostcode($draft->getRecipientPostcode())
            ->setCountry($draft->getRecipientCountry() ?: Polkurier::HOME_COUNTRY)
            ->setEmail($draft->getRecipientEmail())
            ->setPhone($draft->getRecipientPhone());
        if ($draft->getRecipientCompany()) {
            $builder->setCompany($draft->getRecipientCompany());
        }
        if ($draft->getRecipientHouseNumber()) {
            $builder->setHouseNumber($draft->getRecipientHouseNumber());
        }
        if ($draft->getRecipientFlatNumber()) {
            $builder->setFlatNumber($draft->getRecipientFlatNumber());
        }
        if ($draft->getRecipientPointId()) {
            $builder->setPointId($draft->getRecipientPointId());
        }

        return $builder->build();
    }

    /**
     * @throws LocalizedException
     */
    private function mapSender(ShipmentDraftInterface $draft, int $storeId): Address
    {
        // The point the parcels are handed over at is the shipment's, whichever the sender is. It
        // counts only when they're dropped off: a courier or a collective pickup collects them from
        // the sender, so a point left in the draft stays out.
        $pointId = $draft->getPickupMode() === ShipmentDraftInterface::PICKUP_DROP_OFF
            ? $draft->getSenderPointId()
            : null;
        if ($draft->isNewSender()) {
            $sender = $this->senderMapper->mapFromFields($this->getNewSender($draft), $pointId);
        } elseif ($draft->getSenderAddressId()) {
            $sender = $this->senderMapper->mapFromAddress($this->loadAddress($draft->getSenderAddressId()), $pointId);
        } else {
            $sender = $this->senderMapper->mapFromStore($storeId, $pointId);
        }
        if ($sender === null) {
            throw new LocalizedException(__(
                'No sender address: choose one, type one in, or let the store\'s address or shipping origin be used '
                . 'in the Shipments settings.',
            ));
        }

        return $sender;
    }

    /**
     * The sender typed in for this shipment.
     *
     * @return array<string, string> by address field
     * @throws LocalizedException when it lacks what Polkurier needs of a sender
     */
    private function getNewSender(ShipmentDraftInterface $draft): array
    {
        $fields = $draft->getNewSenderAddress();
        $labels = [
            AddressInterface::FIELD_PERSON => __('name'),
            AddressInterface::FIELD_STREET => __('street'),
            AddressInterface::FIELD_HOUSE_NUMBER => __('house number'),
            AddressInterface::FIELD_POSTCODE => __('postcode'),
            AddressInterface::FIELD_CITY => __('city'),
            AddressInterface::FIELD_PHONE => __('phone'),
            AddressInterface::FIELD_EMAIL => __('e-mail'),
        ];
        $missing = \array_map('strval', \array_values(\array_filter(
            $labels,
            static fn(string $field): bool => ($fields[$field] ?? '') === '',
            \ARRAY_FILTER_USE_KEY,
        )));
        if ($missing !== []) {
            throw new LocalizedException(__('Fill in the sender\'s %1.', \implode(', ', $missing)));
        }

        return $fields;
    }

    /**
     * Cash on delivery to a bank account carries the account's number, not its id.
     *
     * @throws LocalizedException
     */
    private function mapCod(ShipmentDraftInterface $draft, int $storeId): Cod
    {
        $returnType = CodReturnTypeEnum::tryFrom((string)$draft->getCodReturnType())
            ?? $this->configProvider->getDefaultCodReturnType($storeId);
        $builder = $this->codBuilderFactory->create()
            ->setAmount(\round((float)$draft->getCodAmount(), 2))
            ->setType(
                CodTypeEnum::tryFrom((string)$draft->getCodType())
                    ?? $this->configProvider->getDefaultCodType($storeId),
            )
            ->setReturnType($returnType);
        if ($returnType === CodReturnTypeEnum::BankAccount && $draft->isNewBankAccount()) {
            $number = BankAccountMapper::normalizeNumber($draft->getNewBankAccountNumber());
            if ($number === '') {
                throw new LocalizedException(__(
                    'Enter the number of the bank account the cash on delivery is paid out to.',
                ));
            }
            $builder->setBankAccount($number);
        } elseif ($returnType === CodReturnTypeEnum::BankAccount) {
            if (!$draft->getCodBankAccountId()) {
                throw new LocalizedException(__('Choose the bank account the cash on delivery is paid out to.'));
            }
            try {
                $builder->setBankAccount($this->bankAccountRepository->get($draft->getCodBankAccountId())->getNumber());
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('The chosen bank account no longer exists.'));
            }
        }

        return $builder->build();
    }

    private function mapPickup(ShipmentDraftInterface $draft): ?Pickup
    {
        // Dropped off, at a point or not, so no courier comes. Stated explicitly, since otherwise
        // Polkurier books a courier for some point carriers when the order is placed.
        return match ($draft->getPickupMode()) {
            ShipmentDraftInterface::PICKUP_DROP_OFF => $this->pickupBuilderFactory->create()
                ->setNoCourierOrder(true)
                ->build(),
            ShipmentDraftInterface::PICKUP_MULTI => $this->pickupBuilderFactory->create()
                ->setMultiPickup(true)
                ->build(),
            default => $this->mapCourierPickup($draft),
        };
    }

    private function mapCourierPickup(ShipmentDraftInterface $draft): ?Pickup
    {
        if (!$draft->getPickupDate()) {
            return null;
        }
        // The date and window are Polish time, as Polkurier offers them, whatever PHP's own zone.
        $zone = PolkurierTimeZoneProvider::getZone();
        $builder = $this->pickupBuilderFactory->create()
            ->setPickupDate(new \DateTimeImmutable($draft->getPickupDate(), $zone));
        if ($draft->getPickupTimeFrom()) {
            $builder->setPickupTimeFrom(
                new \DateTimeImmutable($draft->getPickupDate() . ' ' . $draft->getPickupTimeFrom(), $zone),
            );
        }
        if ($draft->getPickupTimeTo()) {
            $builder->setPickupTimeTo(
                new \DateTimeImmutable($draft->getPickupDate() . ' ' . $draft->getPickupTimeTo(), $zone),
            );
        }

        return $builder->build();
    }

    /**
     * @throws LocalizedException
     */
    private function loadAddress(int $id): AddressInterface
    {
        try {
            return $this->addressRepository->get($id);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('The chosen address no longer exists.'));
        }
    }
}
