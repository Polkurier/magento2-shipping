<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\Documents;
use Polkurier\Shipping\Model\Shipment\Pickups;

/**
 * Shared plumbing of the actions on existing Polkurier shipments: reaching the shipments a request
 * names, one by id or many through a grid selection, and going back to where it came from.
 */
abstract class AbstractShipmentAction extends Action
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        protected readonly ShipmentManagementInterface $management,
        protected readonly ShipmentRepositoryInterface $shipmentRepository,
        protected readonly CollectionFactory $collectionFactory,
        protected readonly Filter $filter,
        protected readonly Documents $documents,
        protected readonly Pickups $pickups,
        protected readonly FileFactory $fileFactory,
    ) {
        parent::__construct($context);
    }

    protected function getShipment(): ?ShipmentInterface
    {
        try {
            return $this->shipmentRepository->get((int)$this->getRequest()->getParam('id'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return null;
        }
    }

    /**
     * The shipments named by ids, selected in a grid, or the one named by id.
     *
     * @return ShipmentInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getSelectedShipments(): array
    {
        $ids = \array_filter(\array_map('intval', \explode(',', (string)$this->getRequest()->getParam('ids'))));
        if ($ids !== []) {
            return \array_values($this->collectionFactory->create()
                ->addFieldToFilter(ShipmentInterface::ENTITY_ID, ['in' => $ids])
                ->getItems());
        }
        if ($this->getRequest()->getParam('id')) {
            $shipment = $this->getShipment();

            return $shipment !== null ? [$shipment] : [];
        }

        $collection = $this->collectionFactory->create();
        $this->filter->getCollection($collection);

        return \array_values($collection->getItems());
    }

    /**
     * @param array{file_name: string, content: string, content_type: string} $document
     * @throws \Exception
     */
    protected function download(array $document): ResponseInterface
    {
        return $this->fileFactory->create(
            $document['file_name'],
            ['type' => 'string', 'value' => $document['content'], 'rm' => false],
            \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
            $document['content_type'],
        );
    }

    protected function back(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $orderId = (int)$this->getRequest()->getParam('order_id');

        return $orderId
            ? $redirect->setPath('sales/order/view', ['order_id' => $orderId])
            : $redirect->setRefererOrBaseUrl();
    }
}
