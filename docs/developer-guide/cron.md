<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Scheduled jobs

[Developer guide](README.md) › Scheduled jobs

Declared in `etc/crontab.xml`, in the `default` group:

| Job | Schedule | Class | Switch |
|---|---|---|---|
| `polkurier_refresh_shipment_status` | `*/30 * * * *` | `Cron\RefreshShipmentStatus` | `sync/status_enabled`, checked per store |
| `polkurier_reconcile_shipments` | `*/10 * * * *` | `Cron\ReconcileShipments` | none: it prevents duplicate shipments |
| `polkurier_synchronize` | `15 * * * *` | `Cron\Synchronize` | `sync/enabled`, and credentials, per website |

Each job does its work through a model you can call yourself:

- `Model\Shipment\StatusRefresher::refreshPending()`: see [Following a shipment](shipments.md#following-a-shipment);
- `Model\Shipment\AutoReconciler::run()`: see [Reconciliation](shipments.md#reconciliation);
- `Api\SynchronizationInterface`: see [Synchronization](synchronization.md).

A failure for one shipment or website is logged and doesn't stop the rest of the run.

To run a job by hand while developing:

```bash
bin/magento cron:run --group=default
```

Next: [Logging](logging.md)
