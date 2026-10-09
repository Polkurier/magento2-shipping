<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\AttentionNotifier;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
use Polkurier\Shipping\Model\Shipment\Status\ReconciliationService;

/**
 * Settles the shipments still undecided, so that none stays unsettled. Those placed without an
 * answer are looked for in Polkurier; those not placed whose last attempt is two days old are
 * removed ({@see FailedShipmentManager}). The admin is then notified of shipments that need
 * attention ({@see AttentionNotifier}).
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
        // These two don't call Polkurier.
        $this->failedShipmentManager->expire();
        try {
            // Like every job that calls Polkurier: only once the account is configured.
            if ($this->configProvider->hasCredentials()) {
                $this->reconciliationService->reconcilePending();
            }
        } finally {
            // Then report what the checks left for someone to handle.
            $this->attentionNotifier->notify();
        }
    }
}
