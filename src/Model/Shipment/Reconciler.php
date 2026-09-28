<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\Order\GetOrdersMessage;
use Polkurier\Webservice\Response\Order;

/**
 * Looks for a Polkurier order whose creation went unanswered.
 *
 * A shipment can't carry the integration's own order identifiers that would make this lookup
 * exact, so the match is strict and heuristic: the same
 * carrier, placed from shortly before the attempt, with the same description (it carries the
 * Magento increment id), recipient postcode and phone, and the same number of parcels, and not
 * already adopted by another shipment. Anything but exactly one match is left to the merchant.
 */
class Reconciler
{
    public const int WINDOW_BEFORE_MINUTES = 10;
    public const int WINDOW_AFTER_HOURS = 24;
    public const int MAX_PAGES = 5;

    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly CollectionFactory $collectionFactory,
    ) {}

    /**
     * @return Order[] the candidates found, best first; empty when none
     * @throws PolkurierException
     */
    public function findCandidates(ShipmentInterface $shipment): array
    {
        $createdAt = new \DateTimeImmutable((string)($shipment->getCreatedAt() ?: 'now'));
        $orders = $this->clientPool->orders($shipment->getStoreId());
        $taken = $this->getAdoptedNumbers((int)$shipment->getId());
        $candidates = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $list = $orders->getOrders(new GetOrdersMessage(
                courier: $shipment->getCarrierCode(),
                dateFrom: $createdAt->modify('-' . self::WINDOW_BEFORE_MINUTES . ' minutes'),
                dateTo: $createdAt->modify('+' . self::WINDOW_AFTER_HOURS . ' hours'),
                pageSize: GetOrdersMessage::MAX_PAGE_SIZE,
                page: $page,
                packs: true,
            ));
            foreach ($list->getResult() as $order) {
                if ($order->getNumber() !== null && !isset($taken[$order->getNumber()]) && $this->matches($shipment, $order)) {
                    $candidates[] = $order;
                }
            }
            if ($page >= $list->getTotalPages()) {
                break;
            }
        }

        return $candidates;
    }

    /**
     * Reads one order by its Polkurier number, e.g. to adopt it by hand.
     *
     * @throws LocalizedException when there is no such order
     * @throws PolkurierException
     */
    public function findByNumber(ShipmentInterface $shipment, string $number): Order
    {
        // Searched by phrase, which matches order numbers too; the exact number is picked below.
        $list = $this->clientPool->orders($shipment->getStoreId())->getOrders(new GetOrdersMessage(
            searchPhrase: $number,
            pageSize: 20,
            packs: true,
        ));
        foreach ($list->getResult() as $order) {
            if ($order->getNumber() === $number) {
                return $order;
            }
        }

        throw new LocalizedException(\__('Polkurier has no order number %1 on this account.', $number));
    }

    public function matches(ShipmentInterface $shipment, Order $order): bool
    {
        if ($order->getCourier() !== $shipment->getCarrierCode()
            || \trim((string)$order->getDescription()) !== \trim($shipment->getDescription())
            || self::postcode($order->getRecipient()->getPostcode()) !== self::postcode((string)$shipment->getRecipientPostcode())
            || self::phone($order->getRecipient()->getPhone()) !== self::phone((string)$shipment->getRecipientPhone())
        ) {
            return false;
        }
        $packs = 0;
        foreach ($order->getPacks() as $pack) {
            $packs += $pack->getAmount();
        }

        return $packs === 0 || $packs === $shipment->getPackCount();
    }

    private static function postcode(string $postcode): string
    {
        return (string)\preg_replace('/\W+/', '', \strtoupper($postcode));
    }

    /**
     * The last nine digits, which survive a country prefix or formatting being added or removed.
     */
    private static function phone(string $phone): string
    {
        return \substr((string)\preg_replace('/\D+/', '', $phone), -9);
    }

    /**
     * @return array<string, true>
     */
    private function getAdoptedNumbers(int $exceptId): array
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::POLKURIER_NUMBER, ['notnull' => true])
            ->addFieldToFilter(ShipmentInterface::ENTITY_ID, ['neq' => $exceptId])
            ->addFieldToFilter(ShipmentInterface::CREATED_AT, ['gt' => (new \DateTimeImmutable('-3 days'))->format('Y-m-d H:i:s')]);
        $numbers = [];
        foreach ($collection->getItems() as $item) {
            $numbers[(string)$item->getPolkurierNumber()] = true;
        }

        return $numbers;
    }
}
