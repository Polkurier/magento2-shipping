<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Service contracts

[Developer guide](README.md) › Service contracts

## Service contracts

The interfaces in `Api/` are the module's public PHP API, marked `@api`; the module has no REST
routes of its own. Prefer them, and plugins on them, over the classes behind them.

| Interface | Purpose |
|---|---|
| `ShipmentManagementInterface` | default draft, create, reconcile, adopt, refresh status, ship the Magento order, cancel |
| `ShipmentRepositoryInterface` | read and save shipments, search by criteria |
| `CarrierConfigRepositoryInterface` | carrier settings, including store view labels |
| `AddressRepositoryInterface`, `BankAccountRepositoryInterface`, `PackTemplateRepositoryInterface` | the synced settings; saving through a repository doesn't push to Polkurier |
| `SynchronizationInterface` | run the sync, for all or some kinds of `Enum\SyncEntityEnum`, or push one entry and get its counts (`Data\SyncResultInterface`) |

Two enums in `Enum/` are part of the API too, also `@api`: `SyncEntityEnum`, the kinds of entries
synchronized, and `ApiEventEnum`, the module's events with their names as values (see
[Events](events.md)).

Data interfaces are in `Api/Data`: `ShipmentInterface`, `ShipmentDraftInterface` with
`ShipmentDraft\PackInterface`, `CarrierConfigInterface` with `CarrierLabelInterface`,
`AddressInterface`, `BankAccountInterface`, `PackTemplateInterface`, `OrderPointInterface`, and
`SyncableInterface` for what the synced entities share.

Every `ShipmentManagementInterface` method turns SDK failures into a `LocalizedException` with a
message a merchant can act on. An unclear outcome doesn't throw: the shipment comes back as
`unknown` and must be reconciled before the order is sent again.

## Order data

An order's pickup point is available as `$order->getExtensionAttributes()->getPolkurierPoint()`
(`OrderPointInterface`), and in REST order responses under `extension_attributes.polkurier_point`.

When the order was shipped with another carrier or point than the customer chose, the order's
shipping method, description and point are the ones it went with, and the customer's choice is
`getPolkurierShippingChoice()` (`ShippingChoiceInterface`), `extension_attributes.polkurier_shipping_choice`
in REST (see [Shipments](shipments.md#the-orders-shipping)).

Next: [Events](events.md)
