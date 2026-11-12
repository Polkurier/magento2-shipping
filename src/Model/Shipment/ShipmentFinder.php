<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\Collection;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * Every look-up of shipments by what they belong to or what state they are in, so each query is
 * written once. Lists come oldest first; "latest" is the one recorded last.
 */
readonly class ShipmentFinder
{
    public function __construct(
        private CollectionFactory $collectionFactory,
    ) {}

    /**
     * The order's latest shipment, of one of the states if any are given.
     *
     * @param string[] $states
     */
    public function findLatestForOrder(int $orderId, array $states = []): ?ShipmentInterface
    {
        return $this->loadFirst(
            $this->query($states)->addFieldToFilter(ShipmentInterface::FIELD_ORDER_ID, ['eq' => $orderId]),
        );
    }

    /**
     * @param string[] $states
     *
     * @return ShipmentInterface[] the order's shipments, of one of the states if any are given
     */
    public function findForOrder(int $orderId, array $states = []): array
    {
        return $this->loadAll(
            $this->query($states)->addFieldToFilter(ShipmentInterface::FIELD_ORDER_ID, ['eq' => $orderId]),
        );
    }

    /**
     * The latest shipment linked to the Magento shipment, of one of the states if any are given. A
     * Magento shipment keeps every Polkurier order it has had: cancelled ones, and the one placed
     * on it since.
     *
     * @param string[] $states
     */
    public function findLatestLinkedTo(int $magentoShipmentId, array $states = []): ?ShipmentInterface
    {
        return $this->loadFirst(
            $this->query($states)->addFieldToFilter(ShipmentInterface::FIELD_SHIPMENT_ID, ['eq' => $magentoShipmentId]),
        );
    }

    /**
     * @param int[] $magentoShipmentIds
     * @param string[] $states
     *
     * @return ShipmentInterface[] the shipments linked to any of the Magento shipments, of one of
     *     the states if any are given
     */
    public function findLinkedTo(array $magentoShipmentIds, array $states = []): array
    {
        if ($magentoShipmentIds === []) {
            return [];
        }

        return $this->loadAll(
            $this->query($states)
                ->addFieldToFilter(ShipmentInterface::FIELD_SHIPMENT_ID, ['in' => \array_values($magentoShipmentIds)]),
        );
    }

    /**
     * @param string[] $numbers Polkurier order numbers
     * @param string[] $states
     *
     * @return ShipmentInterface[] the shipments of those Polkurier orders, of one of the states if
     *     any are given
     */
    public function findByNumbers(array $numbers, array $states = []): array
    {
        if ($numbers === []) {
            return [];
        }

        return $this->loadAll(
            $this->query($states)
                ->addFieldToFilter(ShipmentInterface::FIELD_POLKURIER_NUMBER, ['in' => \array_values($numbers)]),
        );
    }

    /**
     * @param int[] $ids
     *
     * @return ShipmentInterface[]
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->loadAll(
            $this->query([])->addFieldToFilter(ShipmentInterface::FIELD_ENTITY_ID, ['in' => \array_values($ids)]),
        );
    }

    /**
     * The latest shipment with the waybill.
     */
    public function findLatestByWaybill(string $waybill): ?ShipmentInterface
    {
        if ($waybill === '') {
            return null;
        }

        return $this->loadFirst(
            $this->query([])->addFieldToFilter(ShipmentInterface::FIELD_WAYBILLS, ['finset' => $waybill]),
        );
    }

    /**
     * @return ShipmentInterface[] the shipments whose cash on delivery is in that status
     */
    public function findWithCodStatus(string $codStatus): array
    {
        return $this->loadAll(
            $this->query([])->addFieldToFilter(ShipmentInterface::FIELD_COD_STATUS, ['eq' => $codStatus]),
        );
    }

    /**
     * Placed shipments not finished yet, least recently checked first.
     *
     * @param string|null $checkedBefore only those not checked since (UTC), or all
     *
     * @return ShipmentInterface[]
     */
    public function findDueForStatusCheck(?string $checkedBefore, int $limit): array
    {
        $collection = $this->query([ShipmentInterface::STATE_CREATED])
            ->addFieldToFilter(ShipmentInterface::FIELD_IS_FINAL, ['eq' => 0]);
        if ($checkedBefore !== null) {
            $collection->addFieldToFilter(ShipmentInterface::FIELD_STATUS_CHECKED_AT, [
                ['null' => true],
                ['lt' => $checkedBefore],
            ]);
        }

        return $this->loadAll($this->sortCheckedFirst($collection, $limit));
    }

    /**
     * Shipments whose outcome is unknown, or whose request was cut off, attempted before the
     * first time and not checked since the second (UTC), least recently checked first.
     *
     * @return ShipmentInterface[]
     */
    public function findDueForReconciliation(string $attemptedBefore, string $checkedBefore, int $limit): array
    {
        $collection = $this->query([ShipmentInterface::STATE_UNKNOWN, ShipmentInterface::STATE_PENDING])
            ->addFieldToFilter(ShipmentInterface::FIELD_CREATED_AT, ['lt' => $attemptedBefore])
            ->addFieldToFilter(ShipmentInterface::FIELD_STATUS_CHECKED_AT, [
                ['null' => true],
                ['lt' => $checkedBefore],
            ]);

        return $this->loadAll($this->sortCheckedFirst($collection, $limit));
    }

    /**
     * @return ShipmentInterface[] shipments not placed, last attempted before the time (UTC)
     */
    public function findFailedBefore(string $attemptedBefore): array
    {
        return $this->loadAll($this->query([ShipmentInterface::STATE_FAILED])
            ->addFieldToFilter(ShipmentInterface::FIELD_CREATED_AT, ['lt' => $attemptedBefore]));
    }

    /**
     * @param string[] $states
     */
    private function query(array $states): Collection
    {
        $collection = $this->collectionFactory->create();
        if ($states !== []) {
            $collection->addFieldToFilter(ShipmentInterface::FIELD_STATE, ['in' => \array_values($states)]);
        }

        return $collection;
    }

    private function sortCheckedFirst(Collection $collection, int $limit): Collection
    {
        return $collection
            ->setOrder(ShipmentInterface::FIELD_STATUS_CHECKED_AT, Collection::SORT_ORDER_ASC)
            ->setPageSize($limit);
    }

    private function loadFirst(Collection $collection): ?ShipmentInterface
    {
        $items = $collection
            ->setOrder(ShipmentInterface::FIELD_ENTITY_ID, Collection::SORT_ORDER_DESC)
            ->setPageSize(1)
            ->getItems();

        return \reset($items) ?: null;
    }

    /**
     * @return ShipmentInterface[] oldest first, after any order already set
     */
    private function loadAll(Collection $collection): array
    {
        return \array_values(
            $collection->setOrder(ShipmentInterface::FIELD_ENTITY_ID, Collection::SORT_ORDER_ASC)->getItems(),
        );
    }
}
