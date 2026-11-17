<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\Order as MagentoOrder;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Enum\Config\ExternalOrderIdEnum;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Webservice\Builder\Request\Order\GetOrdersMessageBuilder;
use Polkurier\Webservice\Builder\Request\Order\GetOrdersMessageBuilderFactory;
use Polkurier\Webservice\Dto\Request\Order\GetOrdersMessage;
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Enum\Order\OrderStatusCodeEnum;

/**
 * Finds this installation's orders on the Polkurier account: the one an unanswered attempt may have
 * placed, the ones the shop holds the numbers of, and the one with a waybill added to a Magento
 * shipment from elsewhere.
 *
 * Every order the module places carries the installation's application id and the shipment's
 * external order id (the Magento shipment's or order's number). An unanswered attempt's order is
 * looked up by them alone, so an order placed some other way, in the Polkurier panel or by another
 * shop on the account, is never taken for it. The ids aren't unique, e.g. an order's number goes
 * with each of its shipments, so a match must also have the same carrier, description, recipient
 * postcode and phone, and number of parcels, and not be held by another shipment. An order
 * Polkurier lists without its parcels can't be told apart that well: it's only a possible match,
 * never adopted automatically.
 */
readonly class RemoteOrderMatcher
{
    public const int MAX_PAGES = 5;

    private const string SURE = 'sure';
    private const string POSSIBLE = 'possible';
    private const string NO_MATCH = '';

    public function __construct(
        private ShipmentGateway $shipmentGateway,
        private ShipmentFinder $shipmentFinder,
        private DateTime $dateTime,
        private ApplicationIdProvider $applicationIdProvider,
        private GetOrdersMessageBuilderFactory $getOrdersMessageBuilderFactory,
        private ConfigProvider $configProvider,
    ) {}

    /**
     * @return array{sure: Order[], possible: Order[]} orders that match in every respect, and
     *         orders that match but come without their parcels
     * @throws LocalizedException
     */
    public function findCandidates(ShipmentInterface $shipment): array
    {
        $found = [self::SURE => [], self::POSSIBLE => []];
        $externalOrderId = $shipment->getExternalOrderId();
        if ($externalOrderId === null) {
            return $found;
        }
        $orders = $this->findOrders(fn(GetOrdersMessageBuilder $builder) => $builder
            ->externalApplicationId($this->applicationIdProvider->get())
            ->externalOrderId($externalOrderId));
        foreach ($orders as $order) {
            $match = $order->getNumber() !== null ? $this->match($shipment, $order) : self::NO_MATCH;
            if ($match !== self::NO_MATCH) {
                $found[$match][] = $order;
            }
        }

        return $this->findUnclaimed($shipment, $found);
    }

    /**
     * Reads one order by its Polkurier number, e.g. to refresh the shipment that holds it.
     *
     * @throws LocalizedException when there is no such order, or Polkurier can't be asked
     */
    public function findByNumber(string $number): Order
    {
        // Searched by phrase, which matches longer numbers that hold it too; the exact one is
        // picked, on whichever page it comes.
        $orders = $this->findOrders(static fn(GetOrdersMessageBuilder $builder) => $builder
            ->searchPhrase($number)
            ->items(true));
        foreach ($orders as $order) {
            if ($order->getNumber() === $number) {
                return $order;
            }
        }

        throw new LocalizedException(__('Polkurier has no order number %1 on this account.', $number));
    }

    /**
     * The order on the account with the waybill, a live one before a cancelled one, or null.
     *
     * @throws LocalizedException when Polkurier can't be asked
     */
    public function findByWaybill(string $waybill): ?Order
    {
        $found = null;
        // Searched by phrase, which matches longer numbers that hold it too: only the exact one counts.
        $orders = $this->findOrders(static fn(GetOrdersMessageBuilder $builder) => $builder
            ->searchPhrase($waybill)
            ->items(true));
        foreach ($orders as $order) {
            if ($order->getNumber() === null || !self::hasWaybill($order, $waybill)) {
                continue;
            }
            if ($order->getStatusCode() !== OrderStatusCodeEnum::Canceled->value) {
                return $order;
            }
            $found ??= $order;
        }

        return $found;
    }

    /**
     * Whether the order may be the Magento order's: it carries no installation's ids, as one placed
     * in the Polkurier panel, or it carries this installation's id and the Magento order's number, or
     * one of its Magento shipments' while those are what the shop sends. One another installation or
     * integration placed isn't. Order and shipment numbers come from separate sequences, so another
     * order's number can be a shipment's here.
     */
    public function isMadeFor(MagentoOrder $magentoOrder, Order $order): bool
    {
        $applicationId = (string)$order->getExternalApplicationId();
        if ($applicationId === '') {
            return true;
        }
        if ($applicationId !== $this->applicationIdProvider->get()) {
            return false;
        }
        $numbers = [(string)$magentoOrder->getIncrementId()];
        if ($this->configProvider->getExternalOrderId() === ExternalOrderIdEnum::Shipment) {
            foreach ($magentoOrder->getShipmentsCollection() as $magentoShipment) {
                $numbers[] = (string)$magentoShipment->getIncrementId();
            }
        }

        return \in_array((string)$order->getExternalOrderId(), $numbers, true);
    }

    /**
     * Reads the orders of several shipments at once, from the list of this installation's orders
     * placed from the day before the first shipment to the day after the last. The list is read
     * page after page until every order is found, unless its first page shows that it takes as
     * many calls as reading the orders one by one. The orders not found on the pages read are left
     * to {@see self::findByNumber()}: those of a single shipment, those listed later, and the rest
     * when Polkurier fails to list a page.
     *
     * @param ShipmentInterface[] $shipments
     *
     * @return array<string, Order> the orders found, by number
     */
    public function findByNumbers(array $shipments): array
    {
        $wanted = [];
        $placedAt = [];
        foreach ($shipments as $shipment) {
            $number = $shipment->getPolkurierNumber();
            if ($number !== null) {
                $wanted[$number] = true;
                $placedAt[] = $this->getTimestamp($shipment);
            }
        }
        $count = \count($wanted);
        if ($count < 2) {
            return [];
        }
        $from = new \DateTimeImmutable('@' . (\min($placedAt) - 86400));
        $to = new \DateTimeImmutable('@' . (\max($placedAt) + 86400));
        $found = [];
        for ($page = 1; $wanted !== []; $page++) {
            try {
                $list = $this->shipmentGateway->findOrders($this->getOrdersMessageBuilderFactory->create()
                    ->externalApplicationId($this->applicationIdProvider->get())
                    ->dateFrom($from)
                    ->dateTo($to)
                    ->pageSize(GetOrdersMessage::MAX_PAGE_SIZE)
                    ->page($page)
                    ->packs(true)
                    ->items(true)
                    ->build());
            } catch (LocalizedException) {
                break;
            }
            foreach ($list->result as $order) {
                $number = (string)$order->getNumber();
                if (isset($wanted[$number])) {
                    $found[$number] = $order;
                    unset($wanted[$number]);
                }
            }
            if ($page >= $list->totalPages || ($page === 1 && $list->totalPages >= $count)) {
                break;
            }
        }

        return $found;
    }

    /**
     * Whether this installation placed the order for the shipment: the order carries the
     * installation's application id and the shipment's external order id.
     */
    public function isPlacedFor(ShipmentInterface $shipment, Order $order): bool
    {
        return $shipment->getExternalOrderId() !== null
            && $order->getExternalApplicationId() === $this->applicationIdProvider->get()
            && $order->getExternalOrderId() === $shipment->getExternalOrderId();
    }

    /**
     * Whether another shipment already holds the order.
     */
    public function isHeldByAnother(ShipmentInterface $shipment, Order $order): bool
    {
        return $this->getAdoptedNumbers((int)$shipment->getId(), [$order]) !== [];
    }

    /**
     * Without the orders another shipment already holds.
     *
     * @param array{sure: Order[], possible: Order[]} $found
     *
     * @return array{sure: Order[], possible: Order[]}
     */
    private function findUnclaimed(ShipmentInterface $shipment, array $found): array
    {
        $all = \array_merge($found[self::SURE], $found[self::POSSIBLE]);
        $taken = $this->getAdoptedNumbers((int)$shipment->getId(), $all);
        foreach ($found as $kind => $orders) {
            $found[$kind] = \array_values(\array_filter(
                $orders,
                static fn(Order $order): bool => !isset($taken[(string)$order->getNumber()]),
            ));
        }

        return $found;
    }

    /**
     * The orders the search lists, with their parcels, page after page up to MAX_PAGES.
     *
     * @param callable(GetOrdersMessageBuilder): GetOrdersMessageBuilder $filter
     *
     * @return \Generator<Order>
     * @throws LocalizedException
     */
    private function findOrders(callable $filter): \Generator
    {
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $builder = $this->getOrdersMessageBuilderFactory->create()
                ->pageSize(GetOrdersMessage::MAX_PAGE_SIZE)
                ->page($page)
                ->packs(true);
            $list = $this->shipmentGateway->findOrders($filter($builder)->build());
            foreach ($list->result as $order) {
                yield $order;
            }
            if ($page >= $list->totalPages) {
                break;
            }
        }
    }

    /**
     * @return string self::SURE, self::POSSIBLE or self::NO_MATCH
     */
    private function match(ShipmentInterface $shipment, Order $order): string
    {
        $recipient = $order->getRecipient();
        if ($recipient === null
            || !$this->isPlacedFor($shipment, $order)
            || $order->getCourier() !== $shipment->getCarrierCode()
            || \trim((string)$order->getDescription()) !== \trim($shipment->getDescription())
            || self::normalizePostcode($recipient->getPostcode())
                !== self::normalizePostcode((string)$shipment->getRecipientPostcode())
            || self::normalizePhone((string)$recipient->getPhone())
                !== self::normalizePhone((string)$shipment->getRecipientPhone())
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

    /**
     * When the shipment was placed, or now for one not saved yet.
     */
    private function getTimestamp(ShipmentInterface $shipment): int
    {
        $createdAt = $shipment->getCreatedAt();

        return $createdAt
            ? (new \DateTimeImmutable($createdAt, new \DateTimeZone('UTC')))->getTimestamp()
            : $this->dateTime->gmtTimestamp();
    }

    private static function hasWaybill(Order $order, string $waybill): bool
    {
        foreach ($order->getWaybills() as $candidate) {
            if (\strcasecmp($candidate->number, $waybill) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function normalizePostcode(string $postcode): string
    {
        return (string)\preg_replace('/\W+/', '', \strtoupper($postcode));
    }

    /**
     * The last nine digits, which survive a country prefix or formatting being added or removed.
     */
    private static function normalizePhone(string $phone): string
    {
        return \substr((string)\preg_replace('/\D+/', '', $phone), -9);
    }

    /**
     * Which of the orders found another shipment already holds, whenever it was placed.
     *
     * @param Order[] $orders
     *
     * @return array<string, true>
     */
    private function getAdoptedNumbers(int $exceptId, array $orders): array
    {
        if ($orders === []) {
            return [];
        }
        $numbers = [];
        $shipments = $this->shipmentFinder->findByNumbers(
            \array_map(static fn(Order $order): string => (string)$order->getNumber(), $orders),
        );
        foreach ($shipments as $item) {
            if ((int)$item->getId() !== $exceptId) {
                $numbers[(string)$item->getPolkurierNumber()] = true;
            }
        }

        return $numbers;
    }
}
