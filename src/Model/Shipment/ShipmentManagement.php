<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;

/**
 * The shipment service contract, as the admin and the cron jobs use it, over the services that
 * each handle one part of a shipment's life.
 */
readonly class ShipmentManagement implements ShipmentManagementInterface
{
    public function __construct(
        private OwnOrders $ownOrders,
        private DefaultDraftBuilder $draftBuilder,
        private Placing $placing,
        private Reconciliation $reconciliation,
        private StatusRefresh $statusRefresh,
        private Cancellation $cancellation,
    ) {}

    public function getDefaultDraft(int $orderId): ShipmentDraftInterface
    {
        return $this->draftBuilder->createFromOrder($this->ownOrders->get($orderId));
    }

    public function create(int $orderId, ShipmentDraftInterface $draft): ShipmentInterface
    {
        return $this->placing->create($orderId, $draft);
    }

    public function reconcile(int $shipmentId): ShipmentInterface
    {
        return $this->reconciliation->reconcile($shipmentId);
    }

    public function adopt(int $shipmentId, string $polkurierNumber): ShipmentInterface
    {
        return $this->reconciliation->adopt($shipmentId, $polkurierNumber);
    }

    public function refreshStatus(int $shipmentId): ShipmentInterface
    {
        return $this->statusRefresh->refresh($shipmentId);
    }

    public function shipOrder(int $shipmentId): ShipmentInterface
    {
        return $this->statusRefresh->shipOrder($shipmentId);
    }

    public function cancel(int $shipmentId): ShipmentInterface
    {
        return $this->cancellation->cancel($shipmentId);
    }
}
