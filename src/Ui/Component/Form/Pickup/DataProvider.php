<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Pickup;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * The shipments a collective pickup is being booked for.
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * The form narrows by its own request parameter, not through the collection.
     */
    public function addFilter(Filter $filter): void
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getData(): array
    {
        $ids = \array_filter(\array_map('intval', \explode(',', (string)$this->request->getParam('ids'))));
        $numbers = [];
        foreach ($this->collection->addFieldToFilter(ShipmentInterface::ENTITY_ID, ['in' => $ids ?: [0]])->getItems() as $shipment) {
            $numbers[] = \sprintf('%s (%s)', (string)$shipment->getPolkurierNumber(), $shipment->getCarrierCode());
        }

        return [(string)$this->request->getParam('ids') => [
            'ids' => \implode(',', $ids),
            'shipments' => \implode(', ', $numbers),
            'pickup_date' => (new \DateTimeImmutable('+1 weekday'))->format('Y-m-d'),
            'pickup_time_from' => '10:00',
            'pickup_time_to' => '16:00',
        ]];
    }
}
