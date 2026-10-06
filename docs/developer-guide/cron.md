<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Scheduled jobs

[Developer guide](README.md) › Scheduled jobs

Declared in `etc/crontab.xml`, in the module's own cron group, `polkurier` (`etc/cron_groups.xml`),
which runs in a separate process with the default group's timings, so a slow answer from Polkurier
holds up none of Magento's other jobs:

| Job | Schedule | Class | Switch |
|---|---|---|---|
| `polkurier_refresh_shipment_status` | `sync/status_schedule`, `*/30 * * * *` by default | `Cron\RefreshShipmentStatusTask` | `sync/status_enabled`, and credentials |
| `polkurier_reconcile_shipments` | `*/10 * * * *` | `Cron\ReconcileShipmentsTask` | none: it prevents duplicate shipments; credentials for the part asking Polkurier |
| `polkurier_synchronize` | `15 * * * *` | `Cron\SynchronizeTask` | `sync/enabled`, and credentials |
| `polkurier_refresh_carriers` | `45 3 * * *` | `Cron\RefreshCarriersTask` | `sync/enabled`, and credentials |

Each job checks its switch and the credentials itself, and does its work through a model you can
call yourself, whatever the switches say:

- `Model\Shipment\StatusRefresher::refreshPending()`: see [Following a shipment](shipments.md#following-a-shipment);
- `Model\Shipment\AutoReconciler::run()`: see [Reconciliation](shipments.md#reconciliation);
- `Api\SynchronizationInterface`: see [Synchronization](synchronization.md);
- `Model\Carrier\CarrierConfigSynchronizer::synchronize()`: see [Synchronization](synchronization.md#when-it-runs).

A failure for one shipment is logged and doesn't stop the rest of the run. A failed
synchronization is logged, and the next run tries again.

To run a job by hand while developing:

```bash
bin/magento cron:run --group=polkurier
```

Next: [Logging](logging.md)
