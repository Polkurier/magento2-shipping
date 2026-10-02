<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Admin\AttentionNotifier;
use Polkurier\Shipping\Model\Shipment\AutoReconciler;
use Polkurier\Shipping\Model\Shipment\NotPlaced;

/**
 * Settles the shipments still undecided: those placed without an answer are looked for in
 * Polkurier, those not placed two days ago are removed (NotPlaced), so none stays unsettled, and
 * the admin is notified of what needs someone (AttentionNotifier).
 */
class ReconcileShipments
{
    public function __construct(
        private readonly AutoReconciler $autoReconciler,
        private readonly Config $config,
        private readonly NotPlaced $notPlaced,
        private readonly AttentionNotifier $attention,
    ) {}

    public function execute(): void
    {
        // These two ask nothing of Polkurier.
        $this->notPlaced->expire();
        try {
            // As every job asking Polkurier: not before the account is configured.
            if ($this->config->hasCredentials()) {
                $this->autoReconciler->run();
            }
        } finally {
            // What the checks left for someone, after they had their go.
            $this->attention->notify();
        }
    }
}
