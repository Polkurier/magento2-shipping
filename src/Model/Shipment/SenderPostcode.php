<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Mapper\SenderMapper;

/**
 * The postcode a shipment being filled in goes from, for what Polkurier answers by it: the sender
 * typed in, the address chosen, or the store's.
 */
readonly class SenderPostcode
{
    public function __construct(
        private OwnOrders $ownOrders,
        private AddressRepositoryInterface $addressRepository,
        private SenderMapper $senderMapper,
    ) {}

    /**
     * @param array<string, mixed> $data the shipment as the screen has it: order_id,
     *        sender_address_id, new_sender_postcode
     * @throws LocalizedException when there's no postcode yet, or the order isn't one to ship
     */
    public function of(array $data): string
    {
        $storeId = (int)$this->ownOrders->get((int)($data['order_id'] ?? 0))->getStoreId();
        $senderId = (int)($data['sender_address_id'] ?? 0);
        $typedIn = ($data['sender_address_id'] ?? null) === ShipmentDraftInterface::NEW;
        $postcode = match (true) {
            // A sender typed in for the shipment: its postcode once it has one.
            $typedIn => \trim((string)($data['new_sender_postcode'] ?? '')) ?: null,
            $senderId > 0 => $this->addressRepository->get($senderId)->getPostcode(),
            default => $this->senderMapper->fromStore($storeId)?->getPostcode(),
        };
        if ($postcode === null || $postcode === '') {
            throw new LocalizedException($typedIn
                ? \__('Fill in the sender\'s postcode first.')
                : \__('Choose the sender address first.'));
        }

        return $postcode;
    }
}
