<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Service contracts and REST

[Developer guide](README.md) › Service contracts and REST

## Service contracts

The interfaces in `Api/` are the module's public PHP API. Prefer them, and plugins on them, over
the classes behind them.

| Interface | Purpose |
|---|---|
| `ShipmentManagementInterface` | default draft, create, reconcile, adopt, refresh status, ship the Magento order, cancel |
| `ShipmentRepositoryInterface` | read and save shipments, search by criteria |
| `CarrierConfigRepositoryInterface` | carrier settings, including store view labels |
| `AddressRepositoryInterface`, `BankAccountRepositoryInterface`, `PackTemplateRepositoryInterface` | the synced settings; saving through a repository doesn't push to Polkurier |
| `SynchronizationInterface` | run the sync, for all or some kinds of `SyncEntityEnum`, or push one entry |
| `ApiEventEnum` | the module's events, with their names as values (see [Events](events.md)) |

Data interfaces are in `Api/Data`: `ShipmentInterface`, `ShipmentDraftInterface` with
`ShipmentDraft\PackInterface`, `CarrierConfigInterface` with `CarrierLabelInterface`,
`AddressInterface`, `BankAccountInterface`, `PackTemplateInterface`, `OrderPointInterface`, and
`SyncableInterface` for what the synced entities share.

Every `ShipmentManagementInterface` method turns SDK failures into a `LocalizedException` with a
message a merchant can act on. An unclear outcome doesn't throw: the shipment comes back as
`unknown` and must be reconciled before the order is sent again.

## REST routes

Declared in `etc/webapi.xml`, all protected by the module's ACL resources, so an integration needs
an integration token or admin token with those resources.

| Method | Route | Service method | ACL resource |
|---|---|---|---|
| GET | `/V1/polkurier/orders/:orderId/draft` | `getDefaultDraft` | `shipment_create` |
| POST | `/V1/polkurier/orders/:orderId/shipments` | `create` | `shipment_create` |
| POST | `/V1/polkurier/shipments/:shipmentId/reconcile` | `reconcile` | `shipment_create` |
| POST | `/V1/polkurier/shipments/:shipmentId/adopt` | `adopt` (body: `polkurierNumber`) | `shipment_create` |
| POST | `/V1/polkurier/shipments/:shipmentId/ship` | `shipOrder` | `shipment_create` |
| POST | `/V1/polkurier/shipments/:shipmentId/status` | `refreshStatus` | `shipments` |
| POST | `/V1/polkurier/shipments/:shipmentId/cancel` | `cancel` | `shipment_cancel` |
| GET | `/V1/polkurier/shipments/:id` | shipment by id | `shipments` |
| GET | `/V1/polkurier/shipments` | shipments by search criteria | `shipments` |

`orderId` is the order's entity id, not its increment id. `shipmentId` is the id of the Polkurier
shipment (`polkurier_shipment.entity_id`), not of a Magento shipment.

## Placing a shipment from an integration

Fetch the default draft, adjust it, and post it back:

```bash
curl -H "Authorization: Bearer $TOKEN" \
     https://shop.example/rest/V1/polkurier/orders/42/draft > draft.json
```

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d "{\"draft\": $(cat draft.json)}" \
     https://shop.example/rest/V1/polkurier/orders/42/shipments
```

The draft's `items` list the order items and quantities to ship, starting with everything that's
left. Change them to ship part of the order, or send an empty list for everything left.

The answer is the shipment. Check its `state`:

- `created`: done. `polkurier_number`, `waybills` and `shipment_id` are set.
- `unknown`: Polkurier didn't confirm it. Don't post again; call `reconcile` later (the module
  also does that on its own every 10 minutes) and read the shipment again.

A refusal comes back as an HTTP 400 error with Polkurier's reason in the message.

Finding an order's shipments:

```bash
curl -G -H "Authorization: Bearer $TOKEN" https://shop.example/rest/V1/polkurier/shipments \
     --data-urlencode "searchCriteria[filter_groups][0][filters][0][field]=order_id" \
     --data-urlencode "searchCriteria[filter_groups][0][filters][0][value]=42"
```

## Order data

An order's pickup point is available as `$order->getExtensionAttributes()->getPolkurierPoint()`
(`OrderPointInterface`), and in REST order responses under `extension_attributes.polkurier_point`.

Next: [Events](events.md)
