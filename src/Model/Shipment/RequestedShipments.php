<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * The Polkurier shipments an admin request is about: one by id, a list of ids, or a grid selection.
 */
class RequestedShipments
{
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly Filter $filter,
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
        if ($ids !== []) {
            return \array_values($this->collectionFactory->create()
                ->addFieldToFilter(ShipmentInterface::ENTITY_ID, ['in' => $ids])
                ->getItems());
        }
        if ($request->getParam('id')) {
            return [$this->getOne($request)];
        }

        $collection = $this->collectionFactory->create();
        $this->filter->getCollection($collection);

        return \array_values($collection->getItems());
    }
}
