<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Draft;

use Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Shipment\Draft;
use Polkurier\Shipping\Model\Shipment\DraftFactory;

/**
 * Converts a shipment draft to and from the flat array the admin form works with.
 */
readonly class DraftHydrator
{
    public function __construct(
        private DraftFactory $draftFactory,
        private PackFactory $packFactory,
        private ItemFactory $itemFactory,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): ShipmentDraftInterface
    {
        $draft = $this->draftFactory->create();
        $draft->setCarrierCode(self::string($data, ShipmentDraftInterface::FIELD_CARRIER_CODE));
        $draft->setShipmentType(self::string($data, ShipmentDraftInterface::FIELD_SHIPMENT_TYPE));
        $draft->setDescription(self::nullableString($data, ShipmentDraftInterface::FIELD_DESCRIPTION));
        // A sender typed in comes as "new" in place of an address's id, as the screens choose it.
        $newSender = ($data[ShipmentDraftInterface::FIELD_SENDER_ADDRESS_ID] ?? null) === ShipmentDraftInterface::NEW;
        $draft->setNewSender($newSender);
        $draft->setSenderAddressId(
            $newSender ? null : self::nullableInt($data, ShipmentDraftInterface::FIELD_SENDER_ADDRESS_ID),
        );
        $address = [];
        foreach (ShipmentDraftInterface::NEW_SENDER_FIELDS as $field => $key) {
            $address[$field] = self::string($data, $key);
        }
        $draft->setNewSenderAddress($address);
        $draft->setKeepingNewSender(self::flag($data, ShipmentDraftInterface::FIELD_KEEP_NEW_SENDER));
        $draft->setSenderPointId(self::nullableString($data, ShipmentDraftInterface::FIELD_SENDER_POINT_ID));
        $draft->setNewSenderName(self::string($data, ShipmentDraftInterface::FIELD_NEW_SENDER_NAME));
        $draft->setRecipientPerson(self::string($data, ShipmentDraftInterface::FIELD_RECIPIENT_PERSON));
        $draft->setRecipientCompany(self::nullableString($data, ShipmentDraftInterface::FIELD_RECIPIENT_COMPANY));
        $draft->setRecipientStreet(self::string($data, ShipmentDraftInterface::FIELD_RECIPIENT_STREET));
        $draft->setRecipientHouseNumber(
            self::nullableString($data, ShipmentDraftInterface::FIELD_RECIPIENT_HOUSE_NUMBER),
        );
        $draft->setRecipientFlatNumber(
            self::nullableString($data, ShipmentDraftInterface::FIELD_RECIPIENT_FLAT_NUMBER),
        );
        $draft->setRecipientPostcode(self::string($data, ShipmentDraftInterface::FIELD_RECIPIENT_POSTCODE));
        $draft->setRecipientCity(self::string($data, ShipmentDraftInterface::FIELD_RECIPIENT_CITY));
        $draft->setRecipientCountry(
            self::string($data, ShipmentDraftInterface::FIELD_RECIPIENT_COUNTRY) ?: Polkurier::HOME_COUNTRY,
        );
        $draft->setRecipientPhone(self::string($data, ShipmentDraftInterface::FIELD_RECIPIENT_PHONE));
        $draft->setRecipientEmail(self::string($data, ShipmentDraftInterface::FIELD_RECIPIENT_EMAIL));
        $draft->setRecipientPointId(self::nullableString($data, ShipmentDraftInterface::FIELD_RECIPIENT_POINT_ID));
        $draft->setCodAmount(self::nullableFloat($data, ShipmentDraftInterface::FIELD_COD_AMOUNT));
        $draft->setCodType(self::nullableString($data, ShipmentDraftInterface::FIELD_COD_TYPE));
        $draft->setCodReturnType(self::nullableString($data, ShipmentDraftInterface::FIELD_COD_RETURN_TYPE));
        $bankAccountId = $data[ShipmentDraftInterface::FIELD_COD_BANK_ACCOUNT_ID] ?? null;
        $newBankAccount = $bankAccountId === ShipmentDraftInterface::NEW;
        $draft->setNewBankAccount($newBankAccount);
        $draft->setCodBankAccountId(
            $newBankAccount ? null : self::nullableInt($data, ShipmentDraftInterface::FIELD_COD_BANK_ACCOUNT_ID),
        );
        $draft->setNewBankAccountNumber(self::string($data, ShipmentDraftInterface::FIELD_NEW_BANK_ACCOUNT_NUMBER));
        $draft->setKeepingNewBankAccount(self::flag($data, ShipmentDraftInterface::FIELD_KEEP_NEW_BANK_ACCOUNT));
        $draft->setNewBankAccountName(self::string($data, ShipmentDraftInterface::FIELD_NEW_BANK_ACCOUNT_NAME));
        $draft->setInsuranceAmount(self::nullableFloat($data, ShipmentDraftInterface::FIELD_INSURANCE_AMOUNT));
        $draft->setShippingIncluded(self::flag($data, ShipmentDraftInterface::FIELD_SHIPPING_INCLUDED));
        $draft->setCoverAddressId(self::nullableInt($data, ShipmentDraftInterface::FIELD_COVER_ADDRESS_ID));
        $services = (array)($data[ShipmentDraftInterface::FIELD_SERVICES] ?? []);
        $draft->setServices(\array_values(\array_filter(\array_map('strval', $services))));
        $draft->setPickupMode(
            self::string($data, ShipmentDraftInterface::FIELD_PICKUP_MODE) ?: ShipmentDraftInterface::PICKUP_COURIER,
        );
        $draft->setPickupDate(self::nullableString($data, ShipmentDraftInterface::FIELD_PICKUP_DATE));
        $draft->setPickupTimeFrom(self::nullableString($data, ShipmentDraftInterface::FIELD_PICKUP_TIME_FROM));
        $draft->setPickupTimeTo(self::nullableString($data, ShipmentDraftInterface::FIELD_PICKUP_TIME_TO));
        $draft->setAdditionalFields(\array_map('strval', \array_filter(
            (array)($data[ShipmentDraftInterface::FIELD_ADDITIONAL_FIELDS] ?? []),
            static fn($value): bool => \is_scalar($value),
        )));
        $draft->setTargetShipmentId(self::nullableInt($data, ShipmentDraftInterface::FIELD_TARGET_SHIPMENT_ID));

        $packs = [];
        foreach ((array)($data[ShipmentDraftInterface::FIELD_PACKS] ?? []) as $row) {
            if (!\is_array($row) || !empty($row['delete'])) {
                continue;
            }
            $packs[] = $this->packFactory->create()
                ->setItems($this->items((array)($row[PackInterface::FIELD_ITEMS] ?? [])))
                ->setTemplateId(self::nullableInt($row, PackInterface::FIELD_TEMPLATE_ID))
                ->setPackType(self::string($row, PackInterface::FIELD_PACK_TYPE))
                ->setLength((int)($row[PackInterface::FIELD_LENGTH] ?? 0))
                ->setWidth((int)($row[PackInterface::FIELD_WIDTH] ?? 0))
                ->setHeight((int)($row[PackInterface::FIELD_HEIGHT] ?? 0))
                ->setWeight((float)\str_replace(',', '.', (string)($row[PackInterface::FIELD_WEIGHT] ?? 0)))
                ->setKeepAs(self::string($row, PackInterface::FIELD_KEEP_AS))
                // Amount is always 1: identical parcels are listed one by one.
                ->setAmount(1);
        }
        $draft->setPacks($packs);

        $draft->setItems($this->items((array)($data[ShipmentDraftInterface::FIELD_ITEMS] ?? [])));

        return $draft;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(ShipmentDraftInterface $draft): array
    {
        /** @var Draft $draft */
        $data = $draft->getData();
        $data[ShipmentDraftInterface::FIELD_PACKS] = \array_map(
            static fn(PackInterface $pack): array => [
                PackInterface::FIELD_TEMPLATE_ID => $pack->getTemplateId(),
                PackInterface::FIELD_PACK_TYPE => $pack->getPackType(),
                PackInterface::FIELD_LENGTH => $pack->getLength() ?: '',
                PackInterface::FIELD_WIDTH => $pack->getWidth() ?: '',
                PackInterface::FIELD_HEIGHT => $pack->getHeight() ?: '',
                PackInterface::FIELD_WEIGHT => $pack->getWeight(),
                PackInterface::FIELD_AMOUNT => $pack->getAmount(),
                PackInterface::FIELD_ITEMS => self::itemRows($pack->getItems()),
                PackInterface::FIELD_KEEP_AS => $pack->getKeepAs(),
            ],
            $draft->getPacks(),
        );
        $data[ShipmentDraftInterface::FIELD_ITEMS] = self::itemRows($draft->getItems());
        $data[ShipmentDraftInterface::FIELD_PACKS_ESTIMATED] = $draft->isPacksEstimated();
        $data[ShipmentDraftInterface::FIELD_SHIPPING_INCLUDED] = $draft->isShippingIncluded();
        // Chosen on the screens as "new", in place of an id.
        if ($draft->isNewSender()) {
            $data[ShipmentDraftInterface::FIELD_SENDER_ADDRESS_ID] = ShipmentDraftInterface::NEW;
        }
        if ($draft->isNewBankAccount()) {
            $data[ShipmentDraftInterface::FIELD_COD_BANK_ACCOUNT_ID] = ShipmentDraftInterface::NEW;
        }
        unset($data[ShipmentDraftInterface::FIELD_NEW_SENDER], $data[ShipmentDraftInterface::FIELD_NEW_BANK_ACCOUNT]);

        return $data;
    }

    /**
     * @param array<int|string, mixed> $rows of order_item_id and qty
     *
     * @return ItemInterface[]
     */
    private function items(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (!\is_array($row) || !(int)($row[ItemInterface::FIELD_ORDER_ITEM_ID] ?? 0)) {
                continue;
            }
            $items[] = $this->itemFactory->create()
                ->setOrderItemId((int)$row[ItemInterface::FIELD_ORDER_ITEM_ID])
                ->setQty((float)\str_replace(',', '.', (string)($row[ItemInterface::FIELD_QTY] ?? 0)));
        }

        return $items;
    }

    /**
     * @param ItemInterface[] $items
     *
     * @return array<int, array<string, int|float>>
     */
    private static function itemRows(array $items): array
    {
        return \array_map(
            static fn(ItemInterface $item): array => [
                ItemInterface::FIELD_ORDER_ITEM_ID => $item->getOrderItemId(),
                ItemInterface::FIELD_QTY => $item->getQty(),
            ],
            $items,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        return \trim((string)($data[$key] ?? ''));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function flag(array $data, string $key): bool
    {
        return \filter_var($data[$key] ?? false, \FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = self::string($data, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableInt(array $data, string $key): ?int
    {
        $value = self::string($data, $key);

        return $value === '' || !\is_numeric($value) || (int)$value === 0 ? null : (int)$value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nullableFloat(array $data, string $key): ?float
    {
        $value = \str_replace(',', '.', self::string($data, $key));

        return $value === '' || !\is_numeric($value) ? null : (float)$value;
    }
}
