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
use Polkurier\Shipping\Model\Mapper\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Pickups;
use Polkurier\Shipping\Model\Shipment\SenderPostcode;

/**
 * Whether the shipment being filled in can be collected with others later, as Polkurier answers
 * for its carrier, sender and parcels: "available" true or false, or null when it can't be asked
 * yet (no carrier, sender or parcels) or the answer couldn't be had.
 */
class CollectivePickup extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Pickups $pickups,
        private readonly SenderPostcode $senderPostcode,
        private readonly DraftHydrator $hydrator,
        private readonly CreateOrderMessageMapper $messageMapper,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $data = (array)$this->getRequest()->getParam('data', []);
        $carrierCode = (string)($data[ShipmentDraftInterface::CARRIER_CODE] ?? '');
        $packs = $this->hydrator->fromArray([ShipmentDraftInterface::PACKS => (array)($data[ShipmentDraftInterface::PACKS] ?? [])])->getPacks();
        if ($carrierCode === '' || $packs === []) {
            return $result->setData(['available' => null]);
        }
        try {
            $available = $this->pickups->isCollectiveAvailable(
                $carrierCode,
                $this->senderPostcode->of($data),
                (string)($data[ShipmentDraftInterface::SHIPMENT_TYPE] ?? 'box'),
                \array_map($this->messageMapper->mapPack(...), $packs),
            );
        } catch (LocalizedException) {
            // Offered all the same: Polkurier says so when placing, should it not take it.
            return $result->setData(['available' => null]);
        }

        return $result->setData(['available' => $available]);
    }
}
