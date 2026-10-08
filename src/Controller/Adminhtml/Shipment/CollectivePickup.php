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
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\PickupService;
use Polkurier\Shipping\Model\Shipment\Placement\CreateOrderMessageMapper;

/**
 * Asks Polkurier whether the shipment being filled in can be collected with others later, given
 * its carrier, sender and parcels. Answers "available" true or false, or null when it can't be
 * asked yet (no carrier, sender or parcels); "error" and "message" when no answer could be had.
 */
class CollectivePickup extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly PickupService $pickupService,
        private readonly DraftHydrator $draftHydrator,
        private readonly CreateOrderMessageMapper $createOrderMessageMapper,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $carrierCode = (string)($data[ShipmentDraftInterface::FIELD_CARRIER_CODE] ?? '');
        $packs = $this->draftHydrator
            ->fromArray([
                ShipmentDraftInterface::FIELD_PACKS => (array)($data[ShipmentDraftInterface::FIELD_PACKS] ?? []),
            ])
            ->getPacks();
        if ($carrierCode === '' || $packs === []) {
            return ['available' => null];
        }

        // Without an answer, the screen offers it anyway; if it isn't available, Polkurier says so
        // when the order is placed.
        return ['available' => $this->pickupService->isCollectiveAvailable(
            $carrierCode,
            $this->pickupService->senderPostcodeOf($data),
            (string)($data[ShipmentDraftInterface::FIELD_SHIPMENT_TYPE] ?? 'box'),
            \array_map($this->createOrderMessageMapper->mapPack(...), $packs),
        )];
    }
}
