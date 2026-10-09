<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Scheduled jobs

[Developer guide](README.md) › Scheduled jobs

The jobs are declared in `etc/crontab.xml`. They belong to the module's own cron group, `polkurier`
(`etc/cron_groups.xml`). It has the default group's timings but runs in a separate process, so a
slow answer from Polkurier doesn't hold up Magento's other jobs:

| Job | Schedule | Class | Switch |
|---|---|---|---|
| `polkurier_refresh_shipment_status` | `sync/status_schedule`, `*/30 * * * *` by default | [`Cron\RefreshShipmentStatusTask`](../../src/Cron/RefreshShipmentStatusTask.php) | `sync/status_enabled`, and credentials |
| `polkurier_reconcile_shipments` | `*/10 * * * *` | [`Cron\ReconcileShipmentsTask`](../../src/Cron/ReconcileShipmentsTask.php) | none, because it prevents duplicate shipments; the part that asks Polkurier needs credentials |
| `polkurier_synchronize` | `15 * * * *` | [`Cron\SynchronizeTask`](../../src/Cron/SynchronizeTask.php) | `sync/enabled`, and credentials |
| `polkurier_refresh_carriers` | `45 3 * * *` | [`Cron\RefreshCarriersTask`](../../src/Cron/RefreshCarriersTask.php) | `sync/enabled`, and credentials |

Each job checks its switch and the credentials itself, and does its work through a model you can
call yourself, whatever the switches say:

- [`Model\Shipment\Status\StatusRefreshService::refreshPending()`](../../src/Model/Shipment/Status/StatusRefreshService.php):
  see [Following a shipment](shipments.md#following-a-shipment);
- [`Model\Shipment\Status\ReconciliationService::reconcilePending()`](../../src/Model/Shipment/Status/ReconciliationService.php):
  see [Reconciliation](shipments.md#reconciliation);
- [`Model\Sync\Synchronization::synchronize()`](../../src/Model/Sync/Synchronization.php): see
  [Synchronization](synchronization.md);
- [`Model\CarrierConfig\CarrierConfigSynchronizer::synchronize()`](../../src/Model/CarrierConfig/CarrierConfigSynchronizer.php):
  see [Synchronization](synchronization.md#when-it-runs).

A failure for one shipment is logged and doesn't stop the rest of the run. A failed
synchronization is logged, and the next run tries again.

To run a job by hand while developing:

```bash
bin/magento cron:run --group=polkurier
```

Next: [Logging](logging.md)
