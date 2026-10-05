<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Sdk\ApplicationId;
use Polkurier\Shipping\Model\Sdk\ShipmentGatewayInterface;
use Polkurier\Webservice\Request\Order\GetOrdersMessage;
use Polkurier\Webservice\Response\Order;

/**
 * Looks for a Polkurier order whose creation went unanswered.
 *
 * An order sent with the shop's own ids (the installation's application id, and the Magento
 * shipment's or order's number) is looked up by them first. They aren't unique, and Polkurier may
 * not have kept them, so the match is strict all the same, and heuristic without them: the same
 * carrier, placed from the day before the attempt to the day after (Polkurier filters by date
 * only), with the same description (by default it carries the Magento increment id), recipient
 * postcode and phone, and the same number of parcels, and not adopted by another shipment. An
 * order Polkurier lists without its parcels can't be told apart that well: it's only a possible
 * match, never adopted automatically.
 */
readonly class Reconciler
{
    public const int WINDOW_DAYS = 1;
    public const int MAX_PAGES = 5;

    private const string SURE = 'sure';
    private const string POSSIBLE = 'possible';
    private const string NO_MATCH = '';

    public function __construct(
        private ShipmentGatewayInterface $gateway,
        private CollectionFactory $collectionFactory,
        private DateTime $dateTime,
        private ApplicationId $applicationId,
    ) {}

    /**
     * @return array{sure: Order[], possible: Order[]} orders that match in every respect, and
     *         orders that match but come without their parcels
     * @throws LocalizedException
     */
    public function findCandidates(ShipmentInterface $shipment): array
    {
        // Sent with the shop's own ids, it's found by them; Polkurier may have failed to keep
        // them without saying so, so not finding it that way proves nothing.
        $found = $this->findByExternalIds($shipment);
        if ($found['sure'] !== [] || $found['possible'] !== []) {
            return $found;
        }
        $createdAt = new \DateTimeImmutable((string)($shipment->getCreatedAt() ?: '@' . $this->dateTime->gmtTimestamp()));
        $found = ['sure' => [], 'possible' => []];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $list = $this->gateway->findOrders(new GetOrdersMessage(
                courier: $shipment->getCarrierCode(),
                dateFrom: $createdAt->modify('-' . self::WINDOW_DAYS . ' day'),
                dateTo: $createdAt->modify('+' . self::WINDOW_DAYS . ' day'),
                pageSize: GetOrdersMessage::MAX_PAGE_SIZE,
                page: $page,
                packs: true,
            ));
            foreach ($list->getResult() as $order) {
                $match = $order->getNumber() !== null ? $this->match($shipment, $order) : self::NO_MATCH;
                if ($match !== self::NO_MATCH) {
                    $found[$match][] = $order;
                }
            }
            if ($page >= $list->getTotalPages()) {
                break;
            }
        }

        return $this->unclaimed($shipment, $found);
    }

    /**
     * Without the orders another shipment already holds.
     *
     * @param array{sure: Order[], possible: Order[]} $found
     * @return array{sure: Order[], possible: Order[]}
     */
    private function unclaimed(ShipmentInterface $shipment, array $found): array
    {
        $taken = $this->getAdoptedNumbers((int)$shipment->getId(), \array_merge($found['sure'], $found['possible']));
        foreach ($found as $kind => $orders) {
            $found[$kind] = \array_values(\array_filter($orders, static fn(Order $order): bool => !isset($taken[(string)$order->getNumber()])));
        }

        return $found;
    }

    /**
     * The orders Polkurier lists with the ids the shipment was sent with, that match it as well:
     * the ids aren't unique, e.g. an order's number goes with each of its shipments.
     *
     * @return array{sure: Order[], possible: Order[]}
     * @throws LocalizedException
     */
    private function findByExternalIds(ShipmentInterface $shipment): array
    {
        $found = ['sure' => [], 'possible' => []];
        $externalOrderId = $shipment->getExternalOrderId();
        if ($externalOrderId === null) {
            return $found;
        }
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $list = $this->gateway->findOrders(new GetOrdersMessage(
                pageSize: GetOrdersMessage::MAX_PAGE_SIZE,
                page: $page,
                packs: true,
                externalApplicationId: $this->applicationId->get(),
                externalOrderId: $externalOrderId,
            ));
            foreach ($list->getResult() as $order) {
                $match = $order->getNumber() !== null ? $this->match($shipment, $order) : self::NO_MATCH;
                if ($match !== self::NO_MATCH) {
                    $found[$match][] = $order;
                }
            }
            if ($page >= $list->getTotalPages()) {
                break;
            }
        }

        return $this->unclaimed($shipment, $found);
    }

    /**
     * Reads one order by its Polkurier number, e.g. to adopt it by hand.
     *
     * @throws LocalizedException when there is no such order, or Polkurier can't be asked
     */
    public function findByNumber(string $number): Order
    {
        // Searched by phrase, which matches order numbers too; the exact number is picked below.
        $list = $this->gateway->findOrders(new GetOrdersMessage(
            searchPhrase: $number,
            pageSize: 20,
            packs: true,
            items: true,
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
        return $this->match($shipment, $order) === self::SURE;
    }

    /**
     * @return string self::SURE, self::POSSIBLE or self::NO_MATCH
     */
    private function match(ShipmentInterface $shipment, Order $order): string
    {
        if ($order->getCourier() !== $shipment->getCarrierCode()
            || \trim((string)$order->getDescription()) !== \trim($shipment->getDescription())
            || self::postcode($order->getRecipient()->getPostcode()) !== self::postcode((string)$shipment->getRecipientPostcode())
            || self::phone($order->getRecipient()->getPhone()) !== self::phone((string)$shipment->getRecipientPhone())
        ) {
            return self::NO_MATCH;
        }
        $packs = 0;
        foreach ($order->getPacks() as $pack) {
            $packs += $pack->getAmount();
        }

        return match (true) {
            $packs === $shipment->getPackCount() => self::SURE,
            $packs === 0 => self::POSSIBLE,
            default => self::NO_MATCH,
        };
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
     * Which of the orders found another shipment already holds, whenever it was placed.
     *
     * @param Order[] $orders
     * @return array<string, true>
     */
    private function getAdoptedNumbers(int $exceptId, array $orders): array
    {
        if ($orders === []) {
            return [];
        }
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::POLKURIER_NUMBER, ['in' => \array_map(static fn(Order $order): string => (string)$order->getNumber(), $orders)])
            ->addFieldToFilter(ShipmentInterface::ENTITY_ID, ['neq' => $exceptId]);
        $numbers = [];
        foreach ($collection->getItems() as $item) {
            $numbers[(string)$item->getPolkurierNumber()] = true;
        }

        return $numbers;
    }
}
