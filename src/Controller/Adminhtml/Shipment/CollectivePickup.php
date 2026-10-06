<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Mapper\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Pickups;
use Polkurier\Shipping\Model\Shipment\SenderPostcode;

/**
 * Whether the shipment being filled in can be collected with others later, as Polkurier answers
 * for its carrier, sender and parcels: "available" true or false, or null when it can't be asked
 * yet (no carrier, sender or parcels); "error" and "message" when the answer couldn't be had.
 */
class CollectivePickup extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly Pickups $pickups,
        private readonly SenderPostcode $senderPostcode,
        private readonly DraftHydrator $hydrator,
        private readonly CreateOrderMessageMapper $messageMapper,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $carrierCode = (string)($data[ShipmentDraftInterface::CARRIER_CODE] ?? '');
        $packs = $this->hydrator->fromArray([ShipmentDraftInterface::PACKS => (array)($data[ShipmentDraftInterface::PACKS] ?? [])])->getPacks();
        if ($carrierCode === '' || $packs === []) {
            return ['available' => null];
        }

        // When it can't be had, the screen offers it all the same: Polkurier says so when placing.
        return ['available' => $this->pickups->isCollectiveAvailable(
            $carrierCode,
            $this->senderPostcode->of($data),
            (string)($data[ShipmentDraftInterface::SHIPMENT_TYPE] ?? 'box'),
            \array_map($this->messageMapper->mapPack(...), $packs),
        )];
    }
}
