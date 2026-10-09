<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Pickup;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\RequestParam;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Ui\Component\Form\FormDataProvider;

/**
 * The shipments a collective pickup is being booked for.
 */
class DataProvider extends FormDataProvider
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
        private readonly TimezoneInterface $timezone,
        private readonly CarrierNameProvider $carrierNameProvider,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    protected function load(): array
    {
        $ids = \array_filter(\array_map('intval', \explode(',', RequestParam::string($this->request, 'ids'))));
        $numbers = [];
        $shipments = $this->collection
            ->addFieldToFilter(ShipmentInterface::FIELD_ENTITY_ID, ['in' => $ids ?: [0]])
            ->getItems();
        foreach ($shipments as $shipment) {
            $numbers[] = \sprintf(
                '%s (%s)',
                (string)$shipment->getPolkurierNumber(),
                $this->carrierNameProvider->get($shipment->getCarrierCode()),
            );
        }

        return [RequestParam::string($this->request, 'ids') => [
            'ids' => \implode(',', $ids),
            // When booked from an order's Polkurier tab, back to the order afterwards; otherwise to
            // wherever the booking was started from.
            'order_id' => (string)(int)$this->request->getParam('order_id') ?: '',
            'uenc' => RequestParam::string($this->request, 'uenc'),
            'shipments' => \implode(', ', $numbers),
            // The next working day where the store is, not where the server's clock is.
            'pickup_date' => \DateTimeImmutable::createFromMutable($this->timezone->date())
                ->modify('+1 weekday')
                ->format('Y-m-d'),
            'pickup_time_from' => '10:00',
            'pickup_time_to' => '16:00',
        ]];
    }
}
