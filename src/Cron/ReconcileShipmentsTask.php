<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\AttentionNotifier;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
use Polkurier\Shipping\Model\Shipment\Status\ReconciliationService;

/**
 * Settles the shipments still undecided: those placed without an answer are looked for in
 * Polkurier, those not placed two days ago are removed (FailedShipmentManager), so none stays unsettled, and
 * the admin is notified of what needs someone (AttentionNotifier).
 */
readonly class ReconcileShipmentsTask
{
    public function __construct(
        private ReconciliationService $reconciliationService,
        private ConfigProvider $configProvider,
        private FailedShipmentManager $failedShipmentManager,
        private AttentionNotifier $attentionNotifier,
    ) {}

    public function execute(): void
    {
        // These two ask nothing of Polkurier.
        $this->failedShipmentManager->expire();
        try {
            // As every job asking Polkurier: not before the account is configured.
            if ($this->configProvider->hasCredentials()) {
                $this->reconciliationService->reconcilePending();
            }
        } finally {
            // What the checks left for someone, after they had their go.
            $this->attentionNotifier->notify();
        }
    }
}
