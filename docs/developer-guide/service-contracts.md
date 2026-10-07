<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Service contracts

[Developer guide](README.md) › Service contracts

## Service contracts

The interfaces in `Api/` are the module's public PHP API, marked `@api`; the module has no REST
routes of its own. Prefer them, and plugins on them, over the classes behind them. They are what
an integration might need: shipments, drafts, the settings entries and the pickup points. What
only the module itself uses has no interface there: the synchronization with the Polkurier
account (`Model\Sync\Synchronization`), or the customer's original shipping choice
(`Model\ShippingChoice\ShippingChoiceRepository`), which is read through the order's
`polkurier_shipping_choice` extension attribute.

| Interface | Purpose |
|---|---|
| `ShipmentManagementInterface` | default draft, create, reconcile, adopt, refresh status, ship the Magento order, cancel |
| `ShipmentRepositoryInterface` | read and save shipments, search by criteria |
| `CarrierConfigRepositoryInterface` | carrier settings, including store view labels |
| `AddressRepositoryInterface`, `BankAccountRepositoryInterface`, `PackTemplateRepositoryInterface` | the synced settings; saving through a repository doesn't push to Polkurier. Besides `getList()`, the entries a shipment can use, default first, then by name, without those deleted and not synchronized yet: `getSenderAddresses()` and `getDefaultSenderAddress()`, `BankAccountRepositoryInterface::getAvailable()` and `getPreferred()` (the default, or else the first), `PackTemplateRepositoryInterface::getAvailable()` and `getDefault()` |
| `PolkurierClientInterface` | the Polkurier API with the shop's account: the SDK's services (`courier()`, `orders()`, `account()`, `geo()`…), set up as the module uses them; see [Extending](extending.md#calling-polkurier-from-your-own-code) |
| `OrderPointRepositoryInterface`, `QuotePointRepositoryInterface` | the pickup point of an order, and the one chosen at checkout (per shipping address in multi-address checkout); `copyFromQuote()` gives the order the checkout's |

One enum in `Enum/` is part of the API too, also `@api`: `ApiEventEnum`, the module's events with
their names as values (see [Events](events.md)).

Data interfaces are in `Api/Data`: `ShipmentInterface`, `ShipmentDraftInterface` with
`ShipmentDraft\PackInterface`, `CarrierConfigInterface` with `CarrierLabelInterface`,
`AddressInterface`, `BankAccountInterface`, `PackTemplateInterface`, `OrderPointInterface`,
`QuotePointInterface`, and `SyncableInterface` for what the synced entities share. A point's
`getPointCod()` is null when it isn't known whether the point accepts cash on delivery.

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
