<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Shipment\AutoPacker;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\OwnOrders;

/**
 * Packs the items the shipment screen ships into parcels of the pack templates, for the screen to
 * put in place of its parcels.
 */
class Pack extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly OwnOrders $ownOrders,
        private readonly DraftHydrator $hydrator,
        private readonly MagentoShipment $magentoShipment,
        private readonly AutoPacker $autoPacker,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $data = (array)$this->getRequest()->getParam('data', []);
        try {
            $order = $this->ownOrders->get((int)($data['order_id'] ?? 0));
            $draft = $this->hydrator->fromArray($data);
            $qtys = $this->magentoShipment->getQuantities($order, $draft->getItems())
                ?: $this->magentoShipment->getQuantitiesLeft($order);
            if ($qtys === []) {
                throw new LocalizedException(\__('Everything in this order is shipped already.'));
            }
            $packed = $this->autoPacker->pack($order, $qtys, $draft->getShipmentType());
        } catch (LocalizedException $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        }
        if ($packed['packs'] === []) {
            return $result->setData(['error' => true, 'message' => $packed['unmeasured'] !== []
                ? (string)\__('Add shipping dimensions to these products to pack them automatically: %1.', \implode(', ', $packed['unmeasured']))
                : (string)\__('Add a pack template with its size to pack the items automatically.')]);
        }
        $rows = $this->hydrator->toArray($draft->setPacks($packed['packs']));

        return $result->setData([
            'error' => false,
            'packs' => $rows[ShipmentDraftInterface::PACKS],
            'estimated' => true,
            'message' => $packed['unweighed'] !== []
                ? (string)\__('These products have no weight, so their parcels take the pack template\'s weight: %1.', \implode(', ', $packed['unweighed']))
                : '',
        ]);
    }
}
