<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\RefreshDetails as ShipmentRefreshDetails;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\Details\DetailsLoader;
use Polkurier\Shipping\Ui\Component\Form\FormDataProvider;
use Polkurier\Shipping\ViewModel\Adminhtml\ShipmentDetails;

/**
 * The shipment details page: its sections from what is stored, and whether the page should have
 * them read from Polkurier in the background
 * ({@see \Polkurier\Shipping\Controller\Adminhtml\Shipment\RefreshDetails}).
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
        private readonly DetailsLoader $detailsLoader,
        private readonly ShipmentDetails $shipmentDetails,
        private readonly UrlInterface $url,
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

        return [
            $id => [
                'view' => $this->shipmentDetails->buildPage($shipment),
                'state' => $shipment->getState(),
                'refresh' => $this->detailsLoader->isRefreshDue($shipment),
                'urls' => ['refresh' => $this->url->getUrl(ShipmentRefreshDetails::ROUTE, ['id' => $id])],
            ],
        ];
    }
}
