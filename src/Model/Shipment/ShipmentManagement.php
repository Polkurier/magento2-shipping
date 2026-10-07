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
use Polkurier\Shipping\Model\Shipment\Draft\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Placement\PlacementService;
use Polkurier\Shipping\Model\Shipment\Status\ReconciliationService;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;

/**
 * The shipment service contract, as the admin and the cron jobs use it, over the services that
 * each handle one part of a shipment's life.
 */
readonly class ShipmentManagement implements ShipmentManagementInterface
{
    public function __construct(
        private OrderLoader $orderLoader,
        private DefaultDraftBuilder $draftBuilder,
        private PlacementService $placementService,
        private ReconciliationService $reconciliationService,
        private StatusRefreshService $statusRefreshService,
        private CancellationService $cancellationService,
    ) {}

    public function getDefaultDraft(int $orderId): ShipmentDraftInterface
    {
        return $this->draftBuilder->createFromOrder($this->orderLoader->getForPolkurier($orderId));
    }

    public function create(int $orderId, ShipmentDraftInterface $draft): ShipmentInterface
    {
        return $this->placementService->create($orderId, $draft);
    }

    public function reconcile(int $shipmentId): ShipmentInterface
    {
        return $this->reconciliationService->reconcile($shipmentId);
    }

    public function adopt(int $shipmentId, string $polkurierNumber): ShipmentInterface
    {
        return $this->reconciliationService->adopt($shipmentId, $polkurierNumber);
    }

    public function refreshStatus(int $shipmentId): ShipmentInterface
    {
        return $this->statusRefreshService->refresh($shipmentId);
    }

    public function shipOrder(int $shipmentId): ShipmentInterface
    {
        return $this->statusRefreshService->shipOrder($shipmentId);
    }

    public function cancel(int $shipmentId): ShipmentInterface
    {
        return $this->cancellationService->cancel($shipmentId);
    }
}
