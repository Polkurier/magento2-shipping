<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Sdk\Validator;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Exception\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * What a draft is checked for before it's placed, beyond what the form checks: what Polkurier
 * wouldn't refuse, or would refuse only later.
 */
readonly class DraftValidator
{
    public function __construct(
        private CarrierCatalog $catalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private CreateOrderMessageMapper $messageMapper,
        private Validator $validator,
        private ErrorMessageResolver $errorMessageResolver,
        private LoggerInterface $logger,
    ) {}

    /**
     * Settles the sender point, then checks the order the draft makes as the SDK would, before a
     * shipment's number is taken for it, which an order refused as it is would leave unused.
     *
     * @throws LocalizedException
     */
    public function prepare(ShipmentDraftInterface $draft, int $storeId): void
    {
        $this->settleSenderPoint($draft);
        try {
            $this->validator->validate($this->messageMapper->map($draft, $storeId));
        } catch (ValidationException $e) {
            throw $this->errorMessageResolver->exception($e);
        }
    }

    /**
     * The carrier is one the merchant works with, and takes the shipment's type: Polkurier takes a
     * shipment of a type its carrier doesn't, so it's checked here. A carrier list that can't be
     * read leaves it to Polkurier.
     *
     * @throws LocalizedException
     */
    public function assertCarrierTakes(string $carrierCode, ShipmentTypeEnum $type): void
    {
        try {
            $carrier = $this->catalog->getCarrier($carrierCode);
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
     * The point the parcels are handed over at goes only to a carrier that takes them at points,
     * and only when they're dropped off there, or the carrier takes them only at points but not
     * as dropped off; one that takes them only at points gets one, or would refuse the shipment
     * later. A carrier list that can't be read leaves it as it is.
     *
     * @throws LocalizedException
     */
    private function settleSenderPoint(ShipmentDraftInterface $draft): void
    {
        try {
            $carrier = $this->catalog->getCarrier($draft->getCarrierCode());
        } catch (PolkurierException) {
            return;
        }
        if ($carrier === null) {
            return;
        }
        $requirement = $this->carrierFeatureResolver->getSenderPointRequirement($carrier);
        $used = $requirement !== PointRequirementEnum::None && (
            $draft->getPickupMode() === ShipmentDraftInterface::PICKUP_DROP_OFF
            || (
                $requirement === PointRequirementEnum::Required
                && !$this->carrierFeatureResolver->isDropOffAvailable($carrier)
            )
        );
        if (!$used) {
            $draft->setSenderPointId(null);
        } elseif ($requirement === PointRequirementEnum::Required && $draft->getSenderPointId() === null) {
            throw new LocalizedException(__(
                'This carrier takes the parcels at a point: choose the drop-off point you hand them over at.',
            ));
        }
    }
}
