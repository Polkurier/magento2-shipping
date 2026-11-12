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
use Polkurier\Shipping\Model\Sdk\PolkurierTimeZone;
use Polkurier\Webservice\Builder\Order\CodMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\CreateOrderMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\OrderAddressMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\PackMessageBuilderFactory;
use Polkurier\Webservice\Builder\Order\PickupMessageBuilderFactory;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\CodReturnTypeEnum;
use Polkurier\Webservice\Enum\Order\CodTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Request\Order\CodMessage;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Request\Order\OrderAddressMessage;
use Polkurier\Webservice\Request\Order\PackMessage;
use Polkurier\Webservice\Request\Order\PickupMessage;

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
        private CreateOrderMessageBuilderFactory $createOrderMessageBuilderFactory,
        private PackMessageBuilderFactory $packMessageBuilderFactory,
        private OrderAddressMessageBuilderFactory $orderAddressMessageBuilderFactory,
        private CodMessageBuilderFactory $codMessageBuilderFactory,
        private PickupMessageBuilderFactory $pickupMessageBuilderFactory,
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
        $types = ParcelTypeMapper::shipmentTypesOf(\array_map(
            static fn(PackInterface $pack): string => $pack->getPackType(),
            $draft->getPacks(),
        ));
        if (\count($types) > 1) {
            throw new LocalizedException(__(
                'Parcels, envelopes and pallets can\'t go in one shipment: keep to one of them.',
            ));
        }
        $builder = $this->createOrderMessageBuilderFactory->create()
            ->courier($draft->getCarrierCode())
            ->shipmentType($types[0] ?? ShipmentTypeEnum::tryFrom($draft->getShipmentType()) ?? ShipmentTypeEnum::Box)
            ->packs(\array_map($this->mapPack(...), $draft->getPacks()))
            ->sender($this->mapSender($draft, $storeId))
            ->recipient($this->mapRecipient($draft))
            // The API requires a description although the SDK does not.
            ->description(
                (string)\mb_substr((string)$draft->getDescription(), 0, CreateOrderMessage::DESCRIPTION_MAX_LENGTH),
            );

        if ((float)$draft->getCodAmount() > 0) {
            $builder->cod($this->mapCod($draft, $storeId));
        }
        if ((float)$draft->getInsuranceAmount() > 0) {
            $builder->insurance(\round((float)$draft->getInsuranceAmount(), 2));
        }
        if ($draft->getCoverAddressId()) {
            $builder->coverAddress($this->senderMapper->toCoverAddress($this->address($draft->getCoverAddressId())));
        }
        $pickup = $this->mapPickup($draft);
        if ($pickup !== null) {
            $builder->pickup($pickup);
        }
        if (!$draft->getCoverAddressId()
            && \in_array(AdditionalServiceEnum::CoverSenderAddress->value, $draft->getServices(), true)
        ) {
            throw new LocalizedException(__('Choose the address to print on the label as the sender.'));
        }
        $services = $this->mapServices($draft->getServices());
        if ($services !== []) {
            $builder->courierService($services);
        }
        if ($externalIds !== null) {
            $builder->externalApplicationId($externalIds['application'])
                ->externalOrderId($externalIds['id'])
                ->externalOrderNumber($externalIds['number']);
        }
        $fields = \array_filter(
            \array_map('strval', $draft->getAdditionalFields()),
            static fn(string $value): bool => $value !== '',
        );
        if ($fields !== []) {
            $builder->additionalFields($fields);
        }

        return $builder->build();
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

    public function mapPack(PackInterface $pack): PackMessage
    {
        return $this->packMessageBuilderFactory->create()
            ->length($pack->getLength())
            ->width($pack->getWidth())
            ->height($pack->getHeight())
            ->weight(\round($pack->getWeight(), 3))
            ->type(ParcelTypeMapper::polkurierTypeOf($pack->getPackType()))
            ->amount(\max(1, $pack->getAmount()))
            ->build();
    }

    public function mapRecipient(ShipmentDraftInterface $draft): OrderAddressMessage
    {
        $builder = $this->orderAddressMessageBuilderFactory->create()
            ->person($draft->getRecipientPerson())
            ->street($draft->getRecipientStreet())
            ->city($draft->getRecipientCity())
            ->postcode($draft->getRecipientPostcode())
            ->country($draft->getRecipientCountry() ?: Polkurier::HOME_COUNTRY)
            ->email($draft->getRecipientEmail())
            ->phone($draft->getRecipientPhone());
        if ($draft->getRecipientCompany()) {
            $builder->company($draft->getRecipientCompany());
        }
        if ($draft->getRecipientHouseNumber()) {
            $builder->houseNumber($draft->getRecipientHouseNumber());
        }
        if ($draft->getRecipientFlatNumber()) {
            $builder->flatNumber($draft->getRecipientFlatNumber());
        }
        if ($draft->getRecipientPointId()) {
            $builder->pointId($draft->getRecipientPointId());
        }

        return $builder->build();
    }

    /**
     * @throws LocalizedException
     */
    private function mapSender(ShipmentDraftInterface $draft, int $storeId): OrderAddressMessage
    {
        // The point the parcels are handed over at is the shipment's, whichever the sender is. It
        // counts only when they're dropped off: a courier or a collective pickup collects them from
        // the sender, so a point left in the draft stays out.
        $pointId = $draft->getPickupMode() === ShipmentDraftInterface::PICKUP_DROP_OFF
            ? $draft->getSenderPointId()
            : null;
        if ($draft->isNewSender()) {
            $sender = $this->senderMapper->fromFields($this->newSender($draft), $pointId);
        } elseif ($draft->getSenderAddressId()) {
            $sender = $this->senderMapper->fromAddress($this->address($draft->getSenderAddressId()), $pointId);
        } else {
            $sender = $this->senderMapper->fromStore($storeId, $pointId);
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
    private function newSender(ShipmentDraftInterface $draft): array
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
    private function mapCod(ShipmentDraftInterface $draft, int $storeId): CodMessage
    {
        $returnType = CodReturnTypeEnum::tryFrom((string)$draft->getCodReturnType())
            ?? $this->configProvider->getDefaultCodReturnType($storeId);
        $builder = $this->codMessageBuilderFactory->create()
            ->amount(\round((float)$draft->getCodAmount(), 2))
            ->type(
                CodTypeEnum::tryFrom((string)$draft->getCodType())
                    ?? $this->configProvider->getDefaultCodType($storeId),
            )
            ->returnType($returnType);
        if ($returnType === CodReturnTypeEnum::BankAccount && $draft->isNewBankAccount()) {
            $number = BankAccountMapper::normalizeNumber($draft->getNewBankAccountNumber());
            if ($number === '') {
                throw new LocalizedException(__(
                    'Enter the number of the bank account the cash on delivery is paid out to.',
                ));
            }
            $builder->bankAccount($number);
        } elseif ($returnType === CodReturnTypeEnum::BankAccount) {
            if (!$draft->getCodBankAccountId()) {
                throw new LocalizedException(__('Choose the bank account the cash on delivery is paid out to.'));
            }
            try {
                $builder->bankAccount($this->bankAccountRepository->get($draft->getCodBankAccountId())->getNumber());
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('The chosen bank account no longer exists.'));
            }
        }

        return $builder->build();
    }

    private function mapPickup(ShipmentDraftInterface $draft): ?PickupMessage
    {
        // Dropped off, at a point or not, so no courier comes. Stated explicitly, since otherwise
        // Polkurier books a courier for some point carriers when the order is placed.
        return match ($draft->getPickupMode()) {
            ShipmentDraftInterface::PICKUP_DROP_OFF => $this->pickupMessageBuilderFactory->create()
                ->noCourierOrder(true)
                ->build(),
            ShipmentDraftInterface::PICKUP_MULTI => $this->pickupMessageBuilderFactory->create()
                ->multiPickup(true)
                ->build(),
            default => $this->mapCourierPickup($draft),
        };
    }

    private function mapCourierPickup(ShipmentDraftInterface $draft): ?PickupMessage
    {
        if (!$draft->getPickupDate()) {
            return null;
        }
        // The date and window are Polish time, as Polkurier offers them, whatever PHP's own zone.
        $zone = PolkurierTimeZone::zone();
        $builder = $this->pickupMessageBuilderFactory->create()
            ->pickupDate(new \DateTimeImmutable($draft->getPickupDate(), $zone));
        if ($draft->getPickupTimeFrom()) {
            $builder->pickupTimeFrom(
                new \DateTimeImmutable($draft->getPickupDate() . ' ' . $draft->getPickupTimeFrom(), $zone),
            );
        }
        if ($draft->getPickupTimeTo()) {
            $builder->pickupTimeTo(
                new \DateTimeImmutable($draft->getPickupDate() . ' ' . $draft->getPickupTimeTo(), $zone),
            );
        }

        return $builder->build();
    }

    /**
     * @throws LocalizedException
     */
    private function address(int $id): \Polkurier\Shipping\Api\Data\AddressInterface
    {
        try {
            return $this->addressRepository->get($id);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('The chosen address no longer exists.'));
        }
    }
}
