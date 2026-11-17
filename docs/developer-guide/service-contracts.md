<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Service contracts

[Developer guide](README.md) › Service contracts

## Service contracts

The interfaces in `Api/` are the module's public PHP API, marked `@api`; the module has no REST
routes of its own. Prefer them, and plugins on them, over the classes behind them. Parts only the
module itself uses have no interface there, such as the synchronization with the Polkurier account
([`Model\Sync\SettingsSynchronizer`](../../src/Model/Sync/SettingsSynchronizer.php)).

| Interface | Purpose |
|---|---|
| [`ShipmentManagementInterface`](../../src/Api/ShipmentManagementInterface.php) | default draft, create, reconcile, adopt, refresh status, ship the Magento order, cancel |
| [`ShipmentRepositoryInterface`](../../src/Api/ShipmentRepositoryInterface.php) | read and save shipments, search by criteria |
| [`CarrierConfigRepositoryInterface`](../../src/Api/CarrierConfigRepositoryInterface.php) | carrier settings, including store view labels |
| [`AddressRepositoryInterface`](../../src/Api/AddressRepositoryInterface.php), [`BankAccountRepositoryInterface`](../../src/Api/BankAccountRepositoryInterface.php), [`PackTemplateRepositoryInterface`](../../src/Api/PackTemplateRepositoryInterface.php) | the synced settings; saving through a repository doesn't push to Polkurier. Besides `getList()`, these methods return the entries a shipment can use, default first, then by name, leaving out entries deleted but not yet synchronized: `getSenderAddresses()` and `getDefaultSenderAddress()`, [`BankAccountRepositoryInterface::getAvailable()`](../../src/Api/BankAccountRepositoryInterface.php) and `getPreferred()` (the default, or else the first), [`PackTemplateRepositoryInterface::getAvailable()`](../../src/Api/PackTemplateRepositoryInterface.php) and `getDefault()` |
| [`PolkurierClientInterface`](../../src/Api/PolkurierClientInterface.php) | the Polkurier API with the shop's account: every service of the SDK (`getCourier()`, `getOrders()`, `getInvoices()`, `getTickets()`…), set up as the module uses them; see [Extending](extending.md#calling-polkurier-from-your-own-code) |
| [`OrderPointRepositoryInterface`](../../src/Api/OrderPointRepositoryInterface.php), [`QuotePointRepositoryInterface`](../../src/Api/QuotePointRepositoryInterface.php) | the pickup point of an order, and the one chosen at checkout (per shipping address in multi-address checkout); `copyFromQuote()` copies the checkout's point to the order |
| [`ShippingChoiceRepositoryInterface`](../../src/Api/ShippingChoiceRepositoryInterface.php) | the shipping the customer chose, kept while the order shows the carrier or point it was actually sent with; orders also carry it in their `polkurier_shipping_choice` extension attribute |

One enum is part of the API too, also `@api`:
[`Enum\Sdk\ApiEventEnum`](../../src/Enum/Sdk/ApiEventEnum.php), the module's events with their names
as values (see [Events](events.md)).

Data interfaces are in `Api/Data`: [`ShipmentInterface`](../../src/Api/Data/ShipmentInterface.php),
with the keys of its stored order copy and scans in
[`ShipmentDetailsInterface`](../../src/Api/Data/ShipmentDetailsInterface.php),
[`ShipmentDraftInterface`](../../src/Api/Data/ShipmentDraftInterface.php) with
[`ShipmentDraft\PackInterface`](../../src/Api/Data/ShipmentDraft/PackInterface.php),
[`CarrierConfigInterface`](../../src/Api/Data/CarrierConfigInterface.php) with
[`CarrierLabelInterface`](../../src/Api/Data/CarrierLabelInterface.php),
[`AddressInterface`](../../src/Api/Data/AddressInterface.php),
[`BankAccountInterface`](../../src/Api/Data/BankAccountInterface.php),
[`PackTemplateInterface`](../../src/Api/Data/PackTemplateInterface.php),
[`OrderPointInterface`](../../src/Api/Data/OrderPointInterface.php),
[`QuotePointInterface`](../../src/Api/Data/QuotePointInterface.php), and
[`SyncableInterface`](../../src/Api/Data/SyncableInterface.php) for what the synced entities share.
A point's `getPointCod()` is null when it isn't known whether the point accepts cash on delivery.

Every [`ShipmentManagementInterface`](../../src/Api/ShipmentManagementInterface.php) method turns
SDK failures into a `Magento\Framework\Exception\LocalizedException` with a message a merchant can
act on. An unclear outcome doesn't throw: the shipment comes back as `unknown` and must be
reconciled before the order is sent again.

## Order data

An order's pickup point is available as `$order->getExtensionAttributes()->getPolkurierPoint()`
([`OrderPointInterface`](../../src/Api/Data/OrderPointInterface.php)), and in REST order responses
under `extension_attributes.polkurier_point`.

When the order was shipped with a different carrier or point than the customer chose, the order's
shipping method, description and point show what was actually used. The customer's choice is
available as `getPolkurierShippingChoice()`
([`ShippingChoiceInterface`](../../src/Api/Data/ShippingChoiceInterface.php)), or as
`extension_attributes.polkurier_shipping_choice` in REST (see
[Shipments](shipments.md#the-orders-shipping)).

Every order also carries the installation's `external_application_id` as
`getPolkurierApplicationId()`, `extension_attributes.polkurier_application_id` in REST. A Polkurier
order placed for it elsewhere can carry it, with the Magento order's number as
`external_order_id`, to be linked to it (see
[Orders placed elsewhere](shipments.md#orders-placed-elsewhere)).

Next: [Events](events.md)
