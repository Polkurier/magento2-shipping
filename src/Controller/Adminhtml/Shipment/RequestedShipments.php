<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

/**
 * The Polkurier shipments an admin request is about: one by id, a list of ids, or a grid selection.
 */
readonly class RequestedShipments
{
    public function __construct(
        private ShipmentRepositoryInterface $shipmentRepository,
        private ShipmentFinder $shipmentFinder,
        private CollectionFactory $collectionFactory,
        private Filter $filter,
    ) {}

    /**
     * @throws NoSuchEntityException
     */
    public function getOne(RequestInterface $request): ShipmentInterface
    {
        return $this->shipmentRepository->get((int)$request->getParam('id'));
    }

    /**
     * The shipments named by ids, selected in a grid, or the one named by id.
     *
     * @return ShipmentInterface[]
     * @throws LocalizedException
     */
    public function getSelected(RequestInterface $request): array
    {
        $ids = \array_filter(\array_map('intval', \explode(',', (string)$request->getParam('ids'))));
        // Sent from an order's Polkurier tab
        // ({@see \Polkurier\Shipping\Ui\Component\Listing\Shipment\OrderScopedMassAction}): that
        // order's shipments only, whatever else the selection says.
        $orderId = (int)$request->getParam('order_id');
        if ($ids !== []) {
            return \array_values(\array_filter(
                $this->shipmentFinder->byIds($ids),
                static fn(ShipmentInterface $shipment): bool => !$orderId || $shipment->getOrderId() === $orderId,
            ));
        }
        if ($request->getParam('id')) {
            $shipment = $this->getOne($request);

            return $orderId && $shipment->getOrderId() !== $orderId ? [] : [$shipment];
        }
        // A grid selection: the grid's own filters apply.
        $collection = $this->collectionFactory->create();
        $this->filter->getCollection($collection);
        if ($orderId) {
            $collection->addFieldToFilter(ShipmentInterface::FIELD_ORDER_ID, (string)$orderId);
        }

        return \array_values($collection->getItems());
    }
}
