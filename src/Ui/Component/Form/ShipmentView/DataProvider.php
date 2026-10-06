<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\DetailsLoader;
use Polkurier\Shipping\Model\Shipment\DetailsView;
use Polkurier\Shipping\Ui\Component\Form\FormDataProvider;

/**
 * The shipment details page: fills in the local copy of the Polkurier order if it's missing,
 * then hands the page its sections.
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
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly DetailsLoader $loader,
        private readonly DetailsView $view,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    protected function load(): array
    {
        $id = (int)$this->request->getParam('id');
        try {
            $shipment = $this->shipmentRepository->get($id);
        } catch (NoSuchEntityException) {
            return [];
        }
        $loaded = $this->loader->ensure($shipment);

        return [$id => ['view' => $this->view->build($loaded['shipment'], $loaded['errors'])]];
    }
}
