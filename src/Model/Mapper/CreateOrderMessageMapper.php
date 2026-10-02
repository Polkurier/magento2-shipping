<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Mapper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Shipment\PackKinds;
use Polkurier\Webservice\Builder\Order\CodMessageBuilder;
use Polkurier\Webservice\Builder\Order\CreateOrderMessageBuilder;
use Polkurier\Webservice\Builder\Order\OrderAddressMessageBuilder;
use Polkurier\Webservice\Builder\Order\PackMessageBuilder;
use Polkurier\Webservice\Builder\Order\PickupMessageBuilder;
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
class CreateOrderMessageMapper
{
    public function __construct(
        private readonly SenderMapper $senderMapper,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly BankAccountRepositoryInterface $bankAccountRepository,
        private readonly Config $config,
    ) {}

    /**
     * @throws LocalizedException when the draft refers to something that does not exist, or its
     *     parcels are of more than one kind
     */
    public function map(ShipmentDraftInterface $draft, int $storeId): CreateOrderMessage
    {
        $types = PackKinds::shipmentTypesOf(\array_map(
            static fn(PackInterface $pack): string => $pack->getPackType(),
            $draft->getPacks(),
        ));
        if (\count($types) > 1) {
            throw new LocalizedException(\__('Parcels, envelopes and pallets can\'t go in one shipment: keep to one of them.'));
        }
        $builder = CreateOrderMessageBuilder::create()
            ->courier($draft->getCarrierCode())
            ->shipmentType($types[0] ?? ShipmentTypeEnum::tryFrom($draft->getShipmentType()) ?? ShipmentTypeEnum::Box)
            ->packs(\array_map($this->mapPack(...), $draft->getPacks()))
            ->sender($this->mapSender($draft, $storeId))
            ->recipient($this->mapRecipient($draft))
            // The API requires a description although the SDK does not.
            ->description((string)\mb_substr((string)$draft->getDescription(), 0, CreateOrderMessage::DESCRIPTION_MAX_LENGTH));

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
        if (!$draft->getCoverAddressId() && \in_array(AdditionalServiceEnum::CoverSenderAddress->value, $draft->getServices(), true)) {
            throw new LocalizedException(\__('Choose the address to print on the label as the sender.'));
        }
        $services = $this->mapServices($draft->getServices());
        if ($services !== []) {
            $builder->courierService($services);
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
     * @return AdditionalServiceEnum[]
     */
    public function mapServices(array $codes): array
    {
        $services = \array_values(\array_unique(\array_filter(\array_map(
            static fn(string $service): ?AdditionalServiceEnum => AdditionalServiceEnum::tryFrom($service),
            $codes,
        ), static fn(?AdditionalServiceEnum $service): bool => $service !== null && $service !== AdditionalServiceEnum::CoverSenderAddress), \SORT_REGULAR));
        $replaced = [
            AdditionalServiceEnum::SmsOnDispatchWithSenderName->value => AdditionalServiceEnum::SmsOnDispatch,
            AdditionalServiceEnum::SmsOutForDeliveryWithSenderName->value => AdditionalServiceEnum::SmsOutForDelivery,
        ];
        foreach ($services as $service) {
            if (isset($replaced[$service->value])) {
                $plain = $replaced[$service->value];
                $services = \array_filter($services, static fn(AdditionalServiceEnum $candidate): bool => $candidate !== $plain);
            }
        }

        return \array_values($services);
    }

    public function mapPack(PackInterface $pack): PackMessage
    {
        return PackMessageBuilder::create()
            ->length($pack->getLength())
            ->width($pack->getWidth())
            ->height($pack->getHeight())
            ->weight(\round($pack->getWeight(), 3))
            ->type(PackKinds::polkurierTypeOf($pack->getPackType()))
            ->amount(\max(1, $pack->getAmount()))
            ->build();
    }

    public function mapRecipient(ShipmentDraftInterface $draft): OrderAddressMessage
    {
        $builder = OrderAddressMessageBuilder::create()
            ->person($draft->getRecipientPerson())
            ->street($draft->getRecipientStreet())
            ->city($draft->getRecipientCity())
            ->postcode($draft->getRecipientPostcode())
            ->country($draft->getRecipientCountry() ?: 'PL')
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
        if ($draft->getSenderAddressId()) {
            return $this->senderMapper->fromAddress($this->address($draft->getSenderAddressId()));
        }
        $sender = $this->senderMapper->fromStore($storeId);
        if ($sender === null) {
            throw new LocalizedException(\__(
                'No sender address: choose one, add one to Polkurier > Settings > Addresses, or let the store\'s address or shipping origin be used in the Shipments settings.',
            ));
        }

        return $sender;
    }

    /**
     * Cash on delivery to a bank account carries the account's number, not its id.
     *
     * @throws LocalizedException
     */
    private function mapCod(ShipmentDraftInterface $draft, int $storeId): CodMessage
    {
        $returnType = CodReturnTypeEnum::tryFrom((string)$draft->getCodReturnType()) ?? $this->config->getDefaultCodReturnType($storeId);
        $builder = CodMessageBuilder::create()
            ->amount(\round((float)$draft->getCodAmount(), 2))
            ->type(CodTypeEnum::tryFrom((string)$draft->getCodType()) ?? $this->config->getDefaultCodType($storeId))
            ->returnType($returnType);
        if ($returnType === CodReturnTypeEnum::BankAccount) {
            if (!$draft->getCodBankAccountId()) {
                throw new LocalizedException(\__('Choose the bank account the cash on delivery is paid out to.'));
            }
            try {
                $builder->bankAccount($this->bankAccountRepository->get($draft->getCodBankAccountId())->getNumber());
            } catch (NoSuchEntityException) {
                throw new LocalizedException(\__('The chosen bank account no longer exists.'));
            }
        }

        return $builder->build();
    }

    private function mapPickup(ShipmentDraftInterface $draft): ?PickupMessage
    {
        return match ($draft->getPickupMode()) {
            ShipmentDraftInterface::PICKUP_DROP_OFF => PickupMessageBuilder::create()->noCourierOrder(true)->build(),
            ShipmentDraftInterface::PICKUP_MULTI => PickupMessageBuilder::create()->multiPickup(true)->build(),
            default => $this->mapCourierPickup($draft),
        };
    }

    private function mapCourierPickup(ShipmentDraftInterface $draft): ?PickupMessage
    {
        if (!$draft->getPickupDate()) {
            return null;
        }
        $builder = PickupMessageBuilder::create()->pickupDate(new \DateTimeImmutable($draft->getPickupDate()));
        if ($draft->getPickupTimeFrom()) {
            $builder->pickupTimeFrom(new \DateTimeImmutable($draft->getPickupDate() . ' ' . $draft->getPickupTimeFrom()));
        }
        if ($draft->getPickupTimeTo()) {
            $builder->pickupTimeTo(new \DateTimeImmutable($draft->getPickupDate() . ' ' . $draft->getPickupTimeTo()));
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
            throw new LocalizedException(\__('The chosen address no longer exists.'));
        }
    }
}
