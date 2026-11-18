<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\Validator;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Exception\ValidationException;

/**
 * Checks a draft before it's placed, beyond what the form checks: things Polkurier wouldn't refuse,
 * or would refuse only later.
 */
readonly class DraftValidator
{
    public function __construct(
        private CarrierCatalog $carrierCatalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private CreateOrderMessageMapper $createOrderMessageMapper,
        private Validator $validator,
        private ErrorMessageResolver $errorMessageResolver,
        private Logger $logger,
    ) {}

    /**
     * Settles the sender point, then checks the draft's order as the SDK would. This happens before
     * a shipment number is reserved for it, since a refused order would leave that number unused.
     *
     * @throws LocalizedException
     */
    public function prepare(ShipmentDraftInterface $draft, int $storeId): void
    {
        $this->settleSenderPoint($draft);
        try {
            $this->validator->validate($this->createOrderMessageMapper->map($draft, $storeId));
        } catch (ValidationException $e) {
            throw $this->errorMessageResolver->createException($e);
        }
    }

    /**
     * Checks that the carrier is one the merchant works with and takes the shipment's type.
     * Polkurier accepts a shipment of a type its carrier doesn't take, so this is checked here. A
     * carrier list that can't be read leaves the check to Polkurier.
     *
     * @throws LocalizedException
     */
    public function assertCarrierTakes(string $carrierCode, ShipmentTypeEnum $type): void
    {
        try {
            $carrier = $this->carrierCatalog->getCarrier($carrierCode);
        } catch (PolkurierException $e) {
            $this->logger->warning(
                'Polkurier carrier list unavailable to check the shipment type',
                ['exception' => $e],
            );

            return;
        }
        if ($carrier === null) {
            // The account's carriers change: the order's may have gone, or been left out of the
            // carriers worked with, since.
            throw new LocalizedException(__('This carrier is no longer available: choose another.'));
        }
        if (\in_array($type, $this->carrierFeatureResolver->getShipmentTypes($carrier), true)) {
            return;
        }
        throw new LocalizedException(match ($type) {
            ShipmentTypeEnum::Envelope => __('This carrier does not take envelopes.'),
            ShipmentTypeEnum::Palette => __('This carrier does not take pallets.'),
            ShipmentTypeEnum::Box => __('This carrier does not take parcels.'),
        });
    }

    /**
     * A carrier that takes parcels only at points gets them dropped off, at a point that must be
     * given, or it would refuse the shipment later. Other carriers keep the sender's drop-off point
     * only when the parcels are dropped off. A carrier list that can't be read leaves both as they
     * are.
     *
     * @throws LocalizedException
     */
    private function settleSenderPoint(ShipmentDraftInterface $draft): void
    {
        try {
            $carrier = $this->carrierCatalog->getCarrier($draft->getCarrierCode());
        } catch (PolkurierException) {
            return;
        }
        if ($carrier === null) {
            return;
        }
        // No courier collects for a carrier that takes parcels only at points.
        if ($this->carrierFeatureResolver->isDropOffOnly($carrier)) {
            $draft->setPickupMode(ShipmentDraftInterface::PICKUP_DROP_OFF);
        }
        $requirement = $this->carrierFeatureResolver->getSenderPointRequirement($carrier);
        $used = $requirement !== PointRequirementEnum::None
            && $draft->getPickupMode() === ShipmentDraftInterface::PICKUP_DROP_OFF;
        if (!$used) {
            $draft->setSenderPointId(null);
        } elseif ($requirement === PointRequirementEnum::Required && $draft->getSenderPointId() === null) {
            throw new LocalizedException(__(
                'This carrier takes the parcels at a point: choose the drop-off point you hand them over at.',
            ));
        }
    }
}
