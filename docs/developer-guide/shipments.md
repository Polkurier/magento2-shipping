<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Shipments

[Developer guide](README.md) › Shipments

`Api\ShipmentManagementInterface`, implemented by `Model\Shipment\ShipmentManagement`, covers a
shipment's whole life. The admin controllers, Magento's label flow, the cron jobs and the REST
routes all go through it, so whatever you hook into there applies everywhere.

## Lifecycle

```
draft ──create()──► pending ──accepted──────────────► created ──cancel()──► cancelled
                       │                                 ▲
                       ├──refused──► failed              │ reconcile(): found
                       │                                 │
                       └──no clear answer──► unknown ────┘
                                                │
                                                └──reconcile(): not found after 15 min──► failed
```

| State | Constant | Meaning |
|---|---|---|
| `pending` | `STATE_PENDING` | recorded, request on its way |
| `created` | `STATE_CREATED` | accepted by Polkurier |
| `unknown` | `STATE_UNKNOWN` | no clear answer; must be reconciled |
| `failed` | `STATE_FAILED` | refused, or found not to exist; safe to send again |
| `cancelled` | `STATE_CANCELLED` | cancelled on the account |

"Finished" isn't a state: it's the `is_final` flag on a `created` or `cancelled` shipment, and it
only stops the status refresh.

## The draft

A shipment starts as a draft (`Api\Data\ShipmentDraftInterface`, `Model\Shipment\Draft`):
everything the merchant can decide about it. Carrier and shipment type, description, sender,
recipient and pickup point, parcels (`Api\Data\ShipmentDraft\PackInterface`), cash on delivery,
insurance, cover address, services, handover (courier, drop-off or a later collective pickup, with
an optional date and time window) and carrier-specific fields.

`Model\Shipment\DefaultDraftBuilder::createFromOrder()` builds the default draft from the order,
the stored pickup point and the module's settings, narrowed to what the carrier supports.
`ShipmentManagementInterface::getDefaultDraft()` exposes it. `Model\Shipment\DraftHydrator`
converts between drafts and the flat arrays forms work with.

A draft without a sender address is sent from the store: `Model\Mapper\CreateOrderMessageMapper`
takes **Store Information** (`general/store_information/*`) when
`shipments/use_store_information_address` is on, then the shipping origin (`shipping/origin/*`)
when `shipments/use_store_origin_address` is on, each only if complete
(`Model\Mapper\SenderMapper::fromStoreInformation()`, `fromStoreOrigin()`). With neither, the
shipment is refused before anything is sent.

`Model\Mapper\CreateOrderMessageMapper` turns a draft into the SDK's `CreateOrderMessage`. The SDK
validates the message before anything is sent, so an incomplete draft fails with field-level
errors without reaching Polkurier. The other mappers in `Model\Mapper` do the same for addresses,
bank accounts and templates, and `StreetParser` splits Magento's street lines into street, house
number and flat number. Mappers only map: they don't call Polkurier or read the database beyond
what they're given.

## Placing

`create()` refuses when an earlier attempt for the same order is `unknown`, or `pending` and
recent. Otherwise it saves a `polkurier_shipment` row in state `pending`, together with the
serialized request, before anything is sent, so an interrupted request still leaves a trace. What
happens next depends on the answer:

| Outcome | State | What happens |
|---|---|---|
| Accepted | `created` | Polkurier number, waybills, price and tracking link stored; order comment; Magento shipment with tracks; event |
| Refused | `failed` | the reason is stored and thrown as a `LocalizedException` |
| No clear answer | `unknown` | an order comment; nothing else until it's reconciled |

"No clear answer" means a connection error, an unreadable answer, or an error that doesn't say the
order was refused. In those cases the module can't know whether the shipment exists, and sending it
again could create a duplicate.

`placeForMagentoShipment()` is the same without shipping the Magento order, for Magento's own label
flow, which creates the shipment itself. It throws on an unclear answer instead of returning the
`unknown` shipment. See [Shipping labels in Magento](magento-labels.md).

### The Magento shipment

`Model\Shipment\MagentoShipment::ship()` ships whatever is left to ship on the order through
Magento's `ShipOrderInterface`, with one track per waybill, titled with the order's shipping
description and carrying the carrier code `polkurier`. When the order has nothing left to ship, the
waybills are added to its latest shipment instead. The customer is notified if
`shipments/notify_customer` is on.

The Polkurier order exists whatever happens there, so a failure is recorded in `last_error`
rather than thrown. The shipment stays `created` with no `shipment_id`, every status refresh tries
again, and `shipOrder()` does it on demand.

## Reconciliation

`reconcile()` asks `Model\Shipment\Reconciler` for orders on the account that match the recorded
attempt: the same carrier, placed around the same time, with the same description, recipient
postcode, phone and number of parcels.

- **One or more matches**: the earliest is adopted, and the shipment is finished as if it had been
  accepted: Magento shipment, event. Any others are reported in an order comment and in
  `last_error` as probable duplicates.
- **No match**: once the attempt is `ShipmentManagement::NOT_PLACED_AFTER_MINUTES` (15) old, the
  shipment becomes `failed`, which makes it safe to send again. Until then it stays `unknown`,
  because the order may not be listed yet.

`Cron\ReconcileShipments` runs this every 10 minutes through `Model\Shipment\AutoReconciler`, for
`unknown` and `pending` shipments whose attempt is more than
`AutoReconciler::PENDING_AFTER_MINUTES` (5) minutes old. A `pending` shipment that old was cut off
mid-request. `adopt()` links a shipment to a Polkurier order number given by the caller, for
integrations that know it.

## Following a shipment

`refreshStatus()` reads the order from the account and applies it to the shipment through
`Model\Shipment\OrderSnapshot`: waybills, delivery status and date, cash on delivery status and
price. New waybills become new tracks on the Magento shipment. A `created` shipment without a
Magento shipment gets another try at one.

`OrderSnapshot::isFinal()` decides when a shipment is finished:

- cancelled;
- delivered, with no cash on delivery, or with it paid out, settled or offset against a
  compensation;
- returning to the sender with no change for 14 days, since returns don't get a final status of
  their own.

`Model\Shipment\StatusRefresher`, run by `Cron\RefreshShipmentStatus` every 30 minutes, refreshes
`created` shipments that aren't final, in stores with the status sync on, that weren't checked in
the last 30 minutes, least recently checked first, up to `StatusRefresher::BATCH_SIZE` (200) per run. A shipment that fails to refresh
is marked as checked too, so one bad shipment can't hold up the rest.

## Other operations

- `cancel()` cancels a `created` shipment on the account. The Magento shipment and its tracks are
  kept, since Magento can't take a shipment back.
- `Model\Shipment\Documents` fetches labels and handover protocols for one or more shipments of
  one account, in one document.
- `Model\Shipment\Pickups` loads the collection windows for a sender, carrier and date, and books
  collective pickups for shipments placed with `pickup_mode = multi`.
- `Model\Shipment\CostEstimator` prices a draft before it's placed: `quoteAll()` with every
  carrier that can take it, in one request, and `estimate()` with the chosen one. The shipment
  screen uses it for its live prices. A price, not a dry run: a draft that prices cleanly can still
  be refused.
- `Model\Shipment\TrackingLookup` finds a shipment by waybill, for Magento's tracking popup.

Next: [Shipping labels in Magento](magento-labels.md)
