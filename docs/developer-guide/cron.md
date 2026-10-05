<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Scheduled jobs

[Developer guide](README.md) › Scheduled jobs

Declared in `etc/crontab.xml`, in the `default` group:

| Job | Schedule | Class | Switch |
|---|---|---|---|
| `polkurier_refresh_shipment_status` | `sync/status_schedule`, `*/30 * * * *` by default | `Cron\RefreshShipmentStatusTask` | `sync/status_enabled` |
| `polkurier_reconcile_shipments` | `*/10 * * * *` | `Cron\ReconcileShipmentsTask` | none: it prevents duplicate shipments |
| `polkurier_synchronize` | `15 * * * *` | `Cron\SynchronizeTask` | `sync/enabled`, and credentials |
| `polkurier_clean_log_files` | `40 3 * * *` | `Cron\CleanLogFilesTask` | `logging/files_lifetime` (0: off) |

Each job does its work through a model you can call yourself:

- `Model\Shipment\StatusRefresher::refreshPending()`: see [Following a shipment](shipments.md#following-a-shipment);
- `Model\Shipment\AutoReconciler::run()`: see [Reconciliation](shipments.md#reconciliation);
- `Api\SynchronizationInterface`: see [Synchronization](synchronization.md);
- `Model\Sdk\LogFilesCleaner::clean()`: see [Logging](logging.md).

A failure for one shipment is logged and doesn't stop the rest of the run. A failed
synchronization is logged, and the next run tries again.

To run a job by hand while developing:

```bash
bin/magento cron:run --group=default
```

Next: [Logging](logging.md)
