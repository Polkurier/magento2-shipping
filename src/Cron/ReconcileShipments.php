<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Cron;

use Polkurier\Shipping\Model\Shipment\AutoReconciler;

class ReconcileShipments
{
    public function __construct(
        private readonly AutoReconciler $autoReconciler,
    ) {}

    public function execute(): void
    {
        $this->autoReconciler->run();
    }
}
