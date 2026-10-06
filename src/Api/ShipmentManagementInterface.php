<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * Places, tracks and cancels Polkurier orders for Magento orders.
 *
 * Every method that talks to Polkurier turns the SDK's failures into a LocalizedException whose
 * message a merchant can act on. A failure that leaves the outcome unknown does not throw: the
 * shipment comes back in the "unknown" state and must be reconciled before it is sent again. That
 * covers no answer, an unreadable answer, and any error that doesn't clearly say the order was
 * refused.
 *
 * @api
 */
interface ShipmentManagementInterface
{
    /**
     * Places a Polkurier order and, when it is accepted, ships the Magento order with its waybills.
     *
     * @param int $orderId
     * @param \Polkurier\Shipping\Api\Data\ShipmentDraftInterface $draft
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentInterface
     * @throws LocalizedException when the order cannot be shipped this way or Polkurier refused it
     */
    public function create(int $orderId, ShipmentDraftInterface $draft): ShipmentInterface;

    /**
     * The draft a merchant starts from: the order's recipient and point, and the configured defaults.
     *
     * @param int $orderId
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentDraftInterface
     * @throws LocalizedException
     */
    public function getDefaultDraft(int $orderId): ShipmentDraftInterface;

    /**
     * Settles a shipment in the "unknown" state by looking for it in Polkurier.
     *
     * One matching Polkurier order is adopted; none makes the shipment safe to send again; several
     * leave it unknown, for the merchant to decide.
     *
     * @param int $shipmentId
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentInterface
     * @throws LocalizedException
     */
    public function reconcile(int $shipmentId): ShipmentInterface;

    /**
     * Links a shipment whose outcome is unknown to the Polkurier order the merchant identified.
     *
     * @param int $shipmentId
     * @param string $polkurierNumber
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentInterface
     * @throws LocalizedException
     */
    public function adopt(int $shipmentId, string $polkurierNumber): ShipmentInterface;

    /**
     * Reads the current delivery and cash-on-delivery state from Polkurier.
     *
     * @param int $shipmentId
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentInterface
     * @throws LocalizedException
     */
    public function refreshStatus(int $shipmentId): ShipmentInterface;

    /**
     * Ships the Magento order of a placed Polkurier order, with its waybills as tracking numbers,
     * when that did not happen at placing, e.g. because the order could not be shipped then.
     *
     * @param int $shipmentId
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentInterface
     * @throws LocalizedException when the Magento shipment still cannot be created
     */
    public function shipOrder(int $shipmentId): ShipmentInterface;

    /**
     * Cancels the Polkurier order.
     *
     * @param int $shipmentId
     *
     * @return \Polkurier\Shipping\Api\Data\ShipmentInterface
     * @throws LocalizedException when Polkurier refuses, e.g. because the parcel is on its way
     */
    public function cancel(int $shipmentId): ShipmentInterface;
}
