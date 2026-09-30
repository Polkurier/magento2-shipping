<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Shipments

[Developer guide](README.md) › Shipments

`Api\ShipmentManagementInterface`, implemented by `Model\Shipment\ShipmentManagement`, covers a
shipment's whole life. The admin controllers, the cron jobs and the REST routes all go through it,
so whatever you hook into there applies everywhere. It hands each part to a service of its own:

| Service | |
|---|---|
| `Model\Shipment\Placing` | placing (`create()`, and `forMagentoShipment()` for Magento's label flow) |
| `Model\Shipment\Reconciliation` | settling unknown outcomes (`reconcile()`, `adopt()`) |
| `Model\Shipment\StatusRefresh` | reading the order again (`refresh()`), shipping the Magento order (`shipOrder()`) |
| `Model\Shipment\Cancellation` | cancelling |

What each of them does on the Magento side once a Polkurier order is known (the Magento shipment,
the order comments, the `polkurier_api_order_create_after` event for a linked order) is
`Model\Shipment\MagentoFollowUp`. They talk to Polkurier through the shipment gateway (see
[Talking to Polkurier](talking-to-polkurier.md#the-shipment-gateway)).

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

The module ships only orders placed with a Polkurier method, and leaves every other order to its
own carrier. `Model\Shipment\OwnOrders::get()` loads the order for anything that starts a new
shipment (the draft, placing, pricing, packing and collection times, through the admin and the
REST API) and refuses other carriers' orders. Existing Polkurier orders can still be refreshed,
reconciled or cancelled.

`Model\Shipment\DefaultDraftBuilder::createFromOrder()` builds the default draft from the order,
the stored pickup point and the module's settings, narrowed to what the carrier supports.
`ShipmentManagementInterface::getDefaultDraft()` exposes it. `Model\Shipment\DraftHydrator`
converts between drafts and the flat arrays forms work with.

Orders can ship in parts, so the amounts come from `Model\Shipment\OrderAmounts`:

- Cash on delivery is `getCodRemaining()`: the grand total less the `cod_amount` of the order's
  Polkurier shipments in `pending`, `created` or `unknown` (an unconfirmed one may exist). When
  nothing is left, the draft has no cash on delivery.
- Insurance is `getShipmentValue()`. That's the sum of each shipped item's row total with tax,
  less its discount, per unit, times the quantity shipped. Items that ship as part of a parent
  (`isDummy(true)`) and virtual items are left out. When the quantities cover the whole order
  (ordered less cancelled), or match none of its items, it's the grand total. Where the carrier
  requires insurance with cash on delivery, it's at least the COD amount.

`createFromOrder()` takes the quantities as `$qtys` (by order item id). `null` means everything the
order still has to ship, which is what the shipment screen sends. The native label flow passes the
Magento shipment's items.

A draft without a sender address is sent from the store: `Model\Mapper\CreateOrderMessageMapper`
takes **Store Information** (`general/store_information/*`) when
`sender/use_store_information_address` is on, then the shipping origin (`shipping/origin/*`)
when `sender/use_store_origin_address` is on, each only if complete
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
recent. It checks under a lock per order (`polkurier_place_<order id>`, Magento's lock manager),
held until the new attempt is saved, so two requests at once (a double click, two tabs) can't both
pass. Otherwise it saves a `polkurier_shipment` row in state `pending`, together with the
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

`Placing::forMagentoShipment()` is the same without shipping the Magento order, for Magento's own
label flow, which creates the shipment itself. It throws on an unclear answer instead of returning the
`unknown` shipment. See [Shipping labels in Magento](magento-labels.md).

### The Magento shipment

The draft's `items` (`Api\Data\ShipmentDraft\ItemInterface`: `order_item_id`, `qty`) say what the
Magento shipment carries. Leave them empty to ship everything the order has left.
`MagentoShipment::getQuantities()` checks them in `place()` before anything is sent. Every item must
be one the order ships (see `OrderAmounts::getItems()`), no quantity may be more than is left, and
at least one must be above 0. It skips the check when the order has nothing left to ship. The
quantities are stored in `polkurier_shipment.ship_items`, so a Magento shipment made later (reconcile, adopt,
a status refresh, `shipOrder()`) carries the same ones. The native label flow stores the Magento
shipment's quantities there too.

`Model\Shipment\MagentoShipment::ship()` ships those quantities, or whatever is left to ship on the
order, through Magento's `ShipOrderInterface`, with one track per waybill, titled with the order's shipping
description and carrying the carrier code `polkurier`. The customer is notified if
`labels/notify_customer` is on.

Magento shipments and Polkurier orders are one to one: `polkurier_shipment.shipment_id` is
unique. The admin never links them; `MagentoShipment::getTarget()` decides in `place()`, before
anything is sent:

- With items left to ship, a new Magento shipment of the chosen quantities.
- With nothing left, the order's latest Magento shipment that no `pending`, `created` or `unknown`
  Polkurier order holds (`getFreeShipments()`). That's one created without a label, or the
  shipment of a cancelled order that couldn't be removed. It's stored in `target_shipment_id`.
- With neither, the shipment is refused: every Magento shipment already has its Polkurier order.

The draft's parcels can say what each holds (`PackInterface::getItems()`, the same
`ItemInterface` rows). Every parcel is one parcel (`amount` is always 1). `getPackages()` checks
that the parcels hold no more of an item than the shipment carries, and turns them into the
Magento shipment's packages in the form Magento's packaging window saves them: `params`
(container, size, weight in kilograms, customs value) and `items` by order item id (name, weight,
price, quantity). They're stored in `polkurier_shipment.ship_packages` and written to the new
Magento shipment's `packages`, so **Show Packages** works as for a label from Magento's page. The
module writes the field as the plain arrays Magento itself stores, since that's what Magento reads,
although `ShipmentInterface::setPackages()` is typed for package objects. Item weights come from the
order items, converted to kilograms from the store's weight unit.

`Model\Shipment\AutoPacker` packs a shipment's items into parcels. It uses the products' shipping
dimensions (`polkurier_length`, `polkurier_width`, `polkurier_height`, decimal centimetres, added by
`Setup\Patch\Data\AddProductDimensions` in a "Shipping dimensions" group, admin only and removed
when the patch is reverted), read through `Model\Shipment\ItemDimensions`. For the option of a
configurable product it uses the option's product, falling back to the parent's dimensions. A
bundle shipped together uses its own dimensions, or else a box around its parts stacked lying
flat (the largest part's length and width, the sum of their heights per bundle), and stays
unmeasured if any part has no dimensions. The templates are the pack templates with a size.

The packing aims for the fewest parcels, and only uses templates of the draft's shipment type.
`fill()` builds the parcels one at a time. If the smallest template that takes every unit left
exists, it's the last parcel. Otherwise the template that packs the most volume of what's left
makes the next parcel, the smaller one on a tie. A unit that fits no template gets a parcel of its
own size. `merge()` then puts any two parcels whose units fit one template together, until none do.
Finally each parcel takes the smallest template its units fit.

Whether units fit is worked out with guillotine cuts. A box's empty space is a list of disjoint
boxes. Units go in biggest by volume first, each into the empty box it fills best. It's turned the
one of six ways that leaves the smallest gap against a side. What's left of that box is cut into up
to three boxes beside the unit, the longest leftover first, across the whole box. Units never
overlap, and a gap too narrow for a unit never counts as room. `packing/packing_margin`
centimetres are added to each unit's sides first. Guillotine cuts miss some arrangements, so the
result can have a bigger box, or one parcel more, than the best one; a parcel is never overfull. The weight is what the
contents weigh, or the template's when the products have none. It returns nothing, with the reason,
when an item has no dimensions or no template has a size.

`DefaultDraftBuilder` uses it for the default parcels, falling back to one parcel of the default
template. The shipment screen calls it through `Controller\Adminhtml\Shipment\Pack` (**Pack
automatically**), with the quantities chosen.

Going on an existing shipment, `release()` unlinks the cancelled order there and deletes its
waybills from the shipment's tracks, then the new waybills are added. The native label flow does
the same in `Observer\LinkLabelledShipment` when a label is created again for a shipment whose
Polkurier order was cancelled.

The Polkurier order exists whatever happens there, so a failure is recorded in `last_error`
rather than thrown. The shipment stays `created` with no `shipment_id`, every status refresh tries
again, and `shipOrder()` does it on demand.

## Reconciliation

`reconcile()` asks `Model\Shipment\Reconciler` for orders on the account that match the recorded
attempt: the same carrier, placed from the day before to the day after (Polkurier filters orders by
date only), with the same description, recipient postcode, phone and number of parcels, and not
held by another shipment.

- **One or more matches**: the earliest is adopted, and the shipment is finished as if it had been
  accepted: Magento shipment, event. Any others are reported in an order comment and in
  `last_error` as probable duplicates.
- **Only orders listed without their parcels match**: they may be it, but can't be told apart well
  enough. The shipment stays `unknown` with their numbers in `last_error`, to be linked by hand, and
  is never taken as not placed.
- **No match**: once the attempt is `Reconciliation::NOT_PLACED_AFTER_MINUTES` (15) old, the
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
price, and a full local copy of the order (`details`, built by `Model\Shipment\OrderDetails`:
addresses, parcels, cash on delivery, pickup, services and the cost items). New waybills become
new tracks on the Magento shipment. A `created` shipment without a Magento shipment gets another
try at one.

The carrier's scans are read separately, since asking for them makes the call slower:
`Model\Shipment\DetailsLoader` reads them for the details page when they're missing, or older than
an hour for a shipment still on its way, and the manual **Update status** reads them too. The scans
are stored on their own (`ResourceModel\Shipment::updateColumns()`), so opening the page never
writes the rest of the row. A missing order copy is read for the page only: storing it, and acting
on it, is left to `refreshStatus()`, from the cron or **Update status**.

`Model\Shipment\DetailsView` turns the record, the copy (or, before Polkurier has been read, the
stored request, which `Details\SentOrder` reads into the same shape) and the scans into the page's
sections. `Details\Links` finds the Magento order and shipment, `Details\OptionLabels` names the
codes, and `Details\Formatter` writes the values. Times Polkurier reports are in Polish time
(`Formatter::POLKURIER_TIME_ZONE`); they are shown in the admin's time zone like the module's own
UTC times, so both kinds on the page agree.

`OrderSnapshot::isFinal()` decides when a shipment is finished:

- cancelled;
- delivered, with no cash on delivery, or with it paid out, settled or offset against a
  compensation;
- returning to the sender with no change for 14 days, since returns don't get a final status of
  their own.

`Model\Shipment\StatusRefresher`, run by `Cron\RefreshShipmentStatus` every 30 minutes, refreshes
`created` shipments of every store that aren't final and weren't checked in the last 30 minutes,
least recently checked first, up to `StatusRefresher::BATCH_SIZE` (200) per run, when the status
sync is on. A shipment that fails to refresh is marked as checked too, so one bad shipment can't
hold up the rest.

## Other operations

- `cancel()` cancels a `created` shipment on the account, then removes its Magento shipment
  (`MagentoFollowUp::removeShipment()`, `Model\Shipment\MagentoShipmentRemoval`). Magento can't
  cancel a shipment, so it's deleted, with its items, tracks and comments; each item's quantity
  goes off its order item's `qty_shipped`, a `complete` order goes back to `processing`, and the
  Polkurier orders linked to it are unlinked, all in one transaction. A shipment another `pending`,
  `created` or `unknown` Polkurier order holds, or of another order, stays. A failure is logged and
  the cancellation stands, with the Magento shipment still linked; a new Polkurier order then takes
  it over, as below. `StatusRefresh::refresh()` does the same when a refresh finds the order
  cancelled in the Polkurier panel. The comment on the order names the shipment removed.
- `Model\Shipment\Documents` fetches labels and handover protocols for one or more shipments, in
  one document.
- `Model\Shipment\Pickups` loads the collection windows for a sender, carrier and date, and books
  collective pickups for shipments placed with `pickup_mode = multi`.
- `Model\Shipment\CostEstimator` prices a draft before it's placed: `quoteAll()` with every
  carrier that can take it, in one request, and `estimate()` with the chosen one. The shipment
  screen uses it for its live prices. A price, not a dry run: a draft that prices cleanly can still
  be refused. `Model\Shipment\ValuationQuotes` reads the answer, whose shape the SDK leaves open:
  the list Polkurier sends (`Test/Unit/_files/order_valuation_v2.json` is a recorded one), the
  quotes keyed by carrier the SDK documents, or a single quote. Entries it can't read are logged.
- `Model\Shipment\TrackingLookup` finds a shipment by waybill, for Magento's tracking popup.

Next: [Shipping labels in Magento](magento-labels.md)
