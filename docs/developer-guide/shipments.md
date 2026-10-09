<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Shipments

[Developer guide](README.md) › Shipments

A shipment is a Polkurier order placed for a Magento order. Its whole life goes through one service
contract, [`Api\ShipmentManagementInterface`](../../src/Api/ShipmentManagementInterface.php): the
default draft, placing, reconciling, adopting, refreshing the status, shipping the Magento order and
cancelling. The admin controllers use it, so a plugin there sees everything an admin or integration
asks for. Scheduled runs and Magento's label flow don't go through it (see
[Following a shipment](#following-a-shipment) and [Shipping labels in Magento](magento-labels.md)).
Requests reach Polkurier through the shipment gateway (see
[Talking to Polkurier](talking-to-polkurier.md#the-shipment-gateway)).

The module ships only orders placed with a Polkurier method. Anything that starts a new shipment (the
draft, placing, pricing, packing and collection times) refuses other carriers' orders. What follows
from a shipment already made (refreshing, reconciling, cancelling) works on any order.

## Lifecycle

The states are the `STATE_*` constants of
[`Api\Data\ShipmentInterface`](../../src/Api/Data/ShipmentInterface.php):

```
placing ───▶ pending          recorded, the request on its way
             ├── accepted ............................................ created
             ├── failed on Polkurier's side .......................... failed
             ├── refused as it is .................................... not kept
             ├── no clear answer ..................................... unknown
             └── still pending after 2 minutes: cut off .............. taken as unknown

             unknown          may exist on the account
             ├── reconcile() or adopt(): found ....................... created
             ├── reconcile() or adopt(): found cancelled ............. cancelled
             ├── reconcile(): not found, attempt over 15 minutes old . failed
             ├── reconcile(): not found yet .......................... unknown
             └── reconcile(): only orders listed without parcels ..... unknown

             failed           not placed; safe to send again as it is
             ├── placing sends it again .............................. pending
             └── 48 hours after its last attempt, or Discard ......... removed

             created          placed on the account
             └── cancel(), or a refresh finds it cancelled ........... cancelled

             cancelled        cancelled on the account
```

A `failed` shipment sent again and refused as it is stays `failed`, as it was. "Finished" isn't a
state: it's the `is_final` flag on a `created` or `cancelled` shipment, and it only stops the status
refresh.

Load a shipment by id with
[`ShipmentRepositoryInterface`](../../src/Api/ShipmentRepositoryInterface.php).
[`Model\Shipment\ShipmentFinder`](../../src/Model/Shipment/ShipmentFinder.php) looks shipments up by
order, Magento shipment, Polkurier number, waybill, cash on delivery status and state; lists come
oldest first.

## The draft

A shipment starts as a draft
([`Api\Data\ShipmentDraftInterface`](../../src/Api/Data/ShipmentDraftInterface.php)): everything the
merchant can decide about it. Carrier, description, sender, recipient and pickup point, parcels
([`Api\Data\ShipmentDraft\PackInterface`](../../src/Api/Data/ShipmentDraft/PackInterface.php)), cash
on delivery, insurance, cover address, services, handover (courier, drop-off or a later collective
pickup, with an optional date and time window), carrier-specific fields, and whether the customer is
e-mailed once it's placed (`notify_customer`, see [The Magento shipment](#the-magento-shipment)). A
carrier whose `points_map.sender_point` is `REQUIRED` takes parcels only at points: its handover is
always drop-off, whatever the draft says. The default draft is also dropped off when the sender
address has a drop-off point and the carrier takes parcels at points; otherwise a courier collects
it.

The default draft comes from the order, its pickup point and the module's settings, narrowed to what
the carrier supports; `getDefaultDraft()` returns it. The shipment screen, Magento's label flow and
the service contract all build it with
[`Model\Shipment\Draft\DefaultDraftBuilder::createFromOrder()`](../../src/Model/Shipment/Draft/DefaultDraftBuilder.php),
so a plugin there changes every new shipment (see [Extending the
module](extending.md#changing-the-default-shipment)).

**Shipment type.** It follows from the parcels; it isn't chosen. Each parcel is a standard,
non-standard or long parcel, an envelope (`ENV`), or a pallet by its size: `VP` 60 × 40, `PPAL`
80 × 60, `EURO` and `EURO_NST` 120 × 80, `EUR2` 120 × 100, `PAL` 120 × 120, `PA15` 150 × 150.
Parcels of more than one kind are refused, and so is a type the carrier doesn't take
(`available_carriers`' `additional_data.shipmenttype`).

**Amounts.** Orders can ship in parts, so the amounts cover only the part being shipped:

- Cash on delivery is what's left of the grand total after the `cod_amount` of the order's Polkurier
  shipments in `pending`, `created` or `unknown`. When nothing is left, the draft has no cash on
  delivery.
- Insurance is set when `shipment_defaults/insure_by_default` is on, or when the carrier requires
  insurance with cash on delivery. The insured amount is the cash on delivery amount when there is
  one, otherwise the shipment value, so the parts of an order add up to its total either way. The
  shipment value is each shipped item's row total with tax less its discount, per unit, times the
  quantity shipped, leaving out virtual items and items shipped as part of a parent. When the
  quantities cover the whole order, or match none of its items, it's the grand total.

**Sender.** A draft without a sender address is sent from the store: from **Store Information** if
`sender/use_store_information_address` is on, else from the shipping origin if
`sender/use_store_origin_address` is on, whichever is complete first. If neither can be used, the
shipment is refused before anything is sent.

**Validation.** The draft becomes the SDK's `Polkurier\Webservice\Request\Order\CreateOrderMessage`,
and the SDK validates it before anything is sent, so an incomplete draft fails with field-level
errors without reaching Polkurier. To change the message, see
[Extending the module](extending.md#changing-whats-sent).

### Default parcels

With `packing/pack_new_shipments` on, the default parcels are packed automatically into the pack
templates that have a size, aiming for the fewest parcels. The sizes come from the products'
`polkurier_length`, `polkurier_width` and `polkurier_height` attributes (decimal centimetres, admin
only). A configurable option falls back to the parent's dimensions; a bundle shipped together uses
its own, or a box around its parts. The parcels keep to one shipment type: the draft's, or else
`shipment_defaults/default_shipment_type`, or the first type the carrier takes when it doesn't take
that one. Units no template takes get a parcel of their own size. The result is never overfull, but
may use a bigger box or one parcel more than the best fit.

The **Packing** settings shape the result: `packing/packing_margin` is added to each unit's sides,
`packing/parcel_weight` says whether a parcel weighs its contents or its template, and
`packing/weight_limit` set to `keep_under` treats a template's weight as the most its parcel takes.

When packing is off or can't work (an item without dimensions, no template with a size), the draft
gets one parcel of a pack template of the shipment's type, the default one if it is of that type,
or no parcel when there's none.

A pack template's `tare` exists only in Magento and is never synced. When parcels weigh their
contents, the tare is added to what a parcel holds, and taken off the maximum weight a template is
filled up to.

## Placing

Placing is never retried blindly: an earlier attempt whose outcome isn't known is reconciled first.
[`Model\Shipment\Placement\PlacementService`](../../src/Model/Shipment/Placement/PlacementService.php)
checks this under the order's lock (see [One change at a time](#one-change-at-a-time)), so a double
click can't place twice:

```
create()
├── an earlier attempt for the order is unknown ............. refused: reconcile it first
├── an earlier attempt is pending
│   ├── for 2 minutes or less ............................... refused: wait and reload
│   └── for longer: cut off ................................. marked unknown, refused:
│                                                             reconcile it first
├── the draft or its items can't be sent .................... refused, nothing saved
└── saved as pending with the serialized request: the order's failed shipment, or a new one
        │
        sent to Polkurier
        ├── accepted ........................................ created
        ├── failed on Polkurier's side ...................... failed, the reason thrown
        ├── refused as it is ................................ not kept, the reason thrown
        └── no clear answer ................................. unknown
```

Saving the attempt before it's sent means an interrupted request still leaves a trace. A shipment
is an object, not a log of attempts: an order has at most one `failed` shipment, and its next
attempt reuses it.

- **Accepted**: the Polkurier number, waybills, price and tracking link are stored; the order gets a
  comment and a Magento shipment with tracks, and an event is dispatched.
- **Failed on Polkurier's side**: Polkurier was down, busy or had an internal error. The reason and
  the draft are stored, so the shipment can be sent again as it is.
- **Refused as it is**: sending it again unchanged would get the same answer, so nothing of the
  attempt is kept. The reason is thrown as a `Magento\Framework\Exception\LocalizedException`.
- **No clear answer**: an answer lost or unreadable, or an error that doesn't say the order was
  refused. The module can't know whether the shipment exists, and sending it again could create a
  duplicate. The order gets a comment, and nothing else happens until it's reconciled.

Magento's label flow places without shipping the Magento order, since Magento creates the shipment
itself, and an unclear answer throws instead of returning the `unknown` shipment (see
[Shipping labels in Magento](magento-labels.md)).

### The Magento shipment

A placed shipment ships the Magento order through Magento's own
`Magento\Sales\Api\ShipOrderInterface`, with one track per waybill, titled with the order's shipping
description and carrying the carrier code `polkurier`.

The customer's e-mail is the last step, once the Magento shipment is linked
([`Model\Shipment\Native\NativeFollowUpService::ship()`](../../src/Model/Shipment/Native/NativeFollowUpService.php));
`ShipOrderInterface` itself never notifies. Magento's
`Magento\Sales\Model\Order\Email\Sender\ShipmentSender` sends it when all of these hold:

- the draft's `notify_customer` is true (false by default, as on Magento's New Shipment page);
- the Polkurier order is `created`; one `pending`, `unknown`, `failed` or `cancelled` e-mails
  nobody, and one found placed by reconciliation e-mails then;
- the Polkurier order has waybills, as tracks on the Magento shipment; for a carrier that issues
  them later, the e-mail goes when the status refresh adds them;
- no e-mail about that Magento shipment was sent or asked for (its `send_email` and `email_sent`
  are empty), so a replacement on the same shipment doesn't e-mail again;
- the store sends shipment e-mails (`sales_email/shipment/enabled`).

A failure to send is logged, as is `ShipmentSender::send()` returning false while Magento's
asynchronous sending (`sales_email/general/async_sending`) is off; the shipment stands. A Magento
shipment created later, by the status refresh or **Create Magento shipment**, follows the same rules
with the choice stored with the draft.

The draft's `items`
([`Api\Data\ShipmentDraft\ItemInterface`](../../src/Api/Data/ShipmentDraft/ItemInterface.php):
`order_item_id`, `qty`) say what the Magento shipment carries; leave them empty to ship everything
the order has left. While the order has items left to ship, they're checked before anything is
sent: only items the order ships, no more than is left, at least one above 0. They're stored in
`polkurier_shipment.ship_items`, so a Magento shipment made later carries the same quantities.

A Magento shipment has at most one standing (`pending`, `created` or `unknown`) Polkurier order, and
keeps the cancelled ones it had. The target is picked before anything is sent:

- the draft's `target_shipment_id` (set by **Add a Polkurier order** on a Magento shipment's page),
  if no standing Polkurier order holds it;
- with items left to ship, a new Magento shipment of the chosen quantities;
- with nothing left, the order's latest Magento shipment that no standing Polkurier order holds;
- with neither, the shipment is refused.

So no item is shipped twice. When the Polkurier order goes on an existing Magento shipment that had
a cancelled one, the cancelled order's waybills are removed from the shipment's tracks first; the
cancelled order stays linked to it.

The parcels can say what each holds
([`PackInterface::getItems()`](../../src/Api/Data/ShipmentDraft/PackInterface.php)), but no more of
an item than the shipment carries. They're written to the Magento shipment's `packages` in the form
Magento's packaging window saves them (plain arrays, weights in kilograms), so **Show Packages**
works as for a label made on Magento's page.

The Polkurier order exists whatever happens on the Magento side, so a failure there is recorded in
`last_error` rather than thrown. The shipment stays `created` with no `shipment_id`. Once the attempt
is 5 minutes old, every status refresh tries again, and `shipOrder()` does it on demand.

### The order's shipping

An order's shipping method, description and pickup point (`polkurier_order_point`) follow its latest
`created` Polkurier order, so they show what was actually used when it differs from what the
customer chose. The first change keeps the customer's choice, available as the order's
`polkurier_shipping_choice` extension attribute (see
[Service contracts](service-contracts.md#order-data)). When no `created` shipment is left, the
choice is put back on the order. Each change adds a comment to the order's history.

## Reconciliation

Reconciliation settles an attempt whose outcome isn't known: it looks for the order on the account
and links it, or marks the attempt `failed` so it can be sent again. A scheduled job (see
[Scheduled jobs](cron.md)) runs it every 10 minutes for the `unknown` and `pending` shipments whose
attempt is more than 5 minutes old; a `pending` one that old was cut off mid-request. `reconcile()`
does it on demand, and refuses a `pending` attempt younger than 2 minutes, since its request may
still get an answer.

Every order is sent with the shop's own ids, which reconciliation uses to find it:

- `external_application_id` identifies the installation: `general/application_id` if entered, else
  `magento2-` and 12 hex digits from the first encryption key. A staging copy that shares the key
  also shares this id;
- `external_order_id` is the Magento shipment's number or the order's
  (`general/external_order_id`). A new Magento shipment's number is reserved before placing
  (`reserved_shipment_number`), and the shipment gets it once it's made;
- `external_order_number` is the order's number.

Polkurier stores the ids as given (up to 50 characters, not unique, never checked) and may fail to
keep them without saying so. So reconciliation looks for orders on the account with the attempt's
ids, or, failing that, for the same carrier from the day before to the day after. A match must have
the same description, recipient postcode, phone and number of parcels, and not be held by another
shipment:

```
orders on the account that match
├── one or more not cancelled ....................... the earliest adopted: created
├── only cancelled ones ............................. the earliest linked: cancelled
├── only ones listed without their parcels .......... unknown, their numbers in last_error
└── none
    ├── attempt older than 15 minutes ............... failed: safe to send again
    └── younger: it may not be listed yet ........... unknown
```

An adopted order finishes the shipment as if it had been accepted: Magento shipment, event. Any
other live matches are reported in an order comment and in `last_error` as probable duplicates. A
cancelled order is linked without a Magento shipment or event, and the order can be shipped again.
Orders listed without their parcels can't be told apart well enough, so they're left to be linked
by hand.

`adopt()` links an `unknown` shipment to a Polkurier order number given by the caller, for
integrations that know it. A cancelled order linked this way is linked as `cancelled`, as above.

## Shipments that need attention

The reconciliation job also tidies up and warns:

- a `failed` shipment is removed 48 hours after its last attempt, with a note on the order.
  **Discard** removes one at once;
- one major admin notification is added per shipment that still needs someone: `failed` for 24
  hours, or `pending`, `unknown`, or `created` without a Magento shipment, for an hour.

## Following a shipment

`refreshStatus()` reads the order from the account and stores its waybills, delivery status and
date, cash on delivery status and price, and a full local copy of the order (`details`). New
waybills become new tracks on the Magento shipment, followed by the customer's e-mail if it waited
for them (see [The Magento shipment](#the-magento-shipment)), and a `created` shipment without a
Magento shipment gets another try at one. The carrier's scans are read separately, for the details
page and the manual **Update status**.

Times Polkurier reports are in Polish time
([`Model\Sdk\PolkurierTimeZone::ZONE`](../../src/Model/Sdk/PolkurierTimeZone.php)). The status and
delivery times are stored in UTC, like every time the module stores.

A shipment is finished when it's:

- cancelled;
- delivered, with no cash on delivery, or with it paid out, settled or offset against a
  compensation;
- returning to the sender with no change for 14 days, since returns don't get a final status of
  their own.

A scheduled job refreshes statuses on the schedule in `carriers/polkurier/sync/status_schedule`
(every 30 minutes by default), when the status sync is on. It takes `created` shipments that aren't
finished and weren't checked since the previous run, least recently checked first, up to 200 per
run. **Update statuses** on the shipments grid takes the same shipments however recently they were
checked, even with the status sync off.

The scheduled jobs and **Update statuses** call
[`Model\Shipment\Status\StatusRefreshService`](../../src/Model/Shipment/Status/StatusRefreshService.php)
and [`Model\Shipment\Status\ReconciliationService`](../../src/Model/Shipment/Status/ReconciliationService.php)
directly, so a plugin on the service contract doesn't see each shipment of a scheduled run.

### The status webhook

Polkurier's status webhook posts to `polkurier/webhook/shipment_update`, with no form key. It's
checked by its `hash`, an HMAC-SHA256 keyed with `sync/webhook_secret`. Only the event's order
number is used: the `created` shipment with that number goes through `refreshStatus()`, so a
repeated, late or replayed event is harmless. The action answers `OK` once the event is handled
(also for a signed event it can't read, which is logged), 403 for a missing secret or a bad
signature, and 503 when the refresh failed.

## Other operations

- **Cancelling.** `cancel()` cancels a `created` shipment on the account, then clears its Magento
  shipment: the shipment and its items stay, but its label, `packages` and the cancelled order's
  tracks are removed, so another Polkurier order can go on it. A failure there is logged and the
  cancellation stands. A refresh that finds the order cancelled in the Polkurier panel does the
  same.
- **Labels and protocols.**
  [`Model\Shipment\DocumentService`](../../src/Model/Shipment/DocumentService.php) fetches them for
  one or more shipments, in one document. With more than one, it refreshes each first and leaves out
  those that aren't placed, listing why under `left_out`. Labels are PDF unless a
  `Polkurier\Webservice\Enum\Order\LabelFileFormatEnum` is given; the admin's `polkurier/shipment/label`
  takes it as the `format` parameter, from the **Print label** button's list on the shipment details
  page and the **Print labels** submenu of the shipment grids
  ([`Model\Config\Source\LabelFormat`](../../src/Model/Config/Source/LabelFormat.php) lists them).
  In a printer language each order is fetched alone, up to
  `DocumentService::MAX_PRINTER_LANGUAGE_ORDERS` orders per request.
- **Collective pickups.**
  [`Model\Shipment\PickupService`](../../src/Model/Shipment/PickupService.php) loads the collection
  windows for a sender, carrier and date, and books collective pickups for shipments placed with
  `pickup_mode = multi`.
- **Pricing.**
  [`Model\Shipment\Valuation\CostEstimator::quoteAll()`](../../src/Model/Shipment/Valuation/CostEstimator.php)
  prices a draft with every carrier that can take it, in one request. It's only a price, not a dry
  run: a draft that prices cleanly can still be refused. Prices come from `order_valuation_v2`,
  which builds the order as `create_order` does; `order_valuation` gives different prices and
  carriers.

## One change at a time

Only one request at a time changes an order's Polkurier shipments, through Magento's lock manager
with the lock name `polkurier_order_<order id>`. The lock covers placing, `reconcile()`, `adopt()`,
`refreshStatus()`, `shipOrder()` and `cancel()`, so the cron, a webhook and the admin can't both act
on the same Polkurier order. A shipment settled while a request waited for the lock is left as it
is. A request that waits more than 10 seconds fails with a message to try again; the cron jobs log
it and leave the shipment to their next run.

## Entries typed in for a shipment

A sender or a cash on delivery account can be typed in rather than chosen: the screens send
`sender_address_id` or `cod_bank_account_id` as `new`
([`ShipmentDraftInterface::NEW`](../../src/Api/Data/ShipmentDraftInterface.php)), with the typed
fields flat next to the recipient's (`new_sender_*`, listed in `NEW_SENDER_FIELDS`, and
`new_bank_account_number`). A parcel without a template can carry `keep_as`, the pack template name
to save it under (`polkurier_keep_as` in a package's params on Magento's packaging window).

`keep_new_sender`, `keep_new_bank_account` and `keep_as` ask for them to be saved for later. The
entries the admin isn't allowed to change are dropped, with a warning. Once Polkurier has accepted
the order, each entry is saved as if on its settings page, so it's pushed to Polkurier the same way.
A save that fails never undoes the shipment. The default draft starts with a typed-in sender when
there's no address to fall back on, and with a typed-in account when cash on delivery has no
account to go to; both are marked to be kept.

Next: [Shipping labels in Magento](magento-labels.md)
