<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Shipments

[Developer guide](README.md) › Shipments

`Api\ShipmentManagementInterface`, implemented by `Model\Shipment\ShipmentManagement`, covers a
shipment's whole life. The admin controllers and the cron jobs all go through it,
so whatever you hook into there applies everywhere. It hands each part to a service of its own:

| Service | |
|---|---|
| `Model\Shipment\Placement\PlacementService` | placing (`create()`, and `forNativeShipment()` for Magento's label flow) |
| `Model\Shipment\Status\ReconciliationService` | settling unknown outcomes (`reconcile()`, `adopt()`) |
| `Model\Shipment\Status\StatusRefreshService` | reading the order again (`refresh()`), shipping the Magento order (`shipOrder()`) |
| `Model\Shipment\CancellationService` | cancelling |

What each of them does on the Magento side once a Polkurier order is known (the Magento shipment,
the order comments, the `polkurier_api_order_create_after` event for a linked order) is
`Model\Shipment\Native\NativeFollowUpService`. They talk to Polkurier through the shipment gateway (see
[Talking to Polkurier](talking-to-polkurier.md#the-shipment-gateway)).

## Lifecycle

```
draft ──create()──► pending ──accepted──────────────► created ──cancel()──► cancelled
                       │                                 ▲
                       ├──failed on Polkurier's side──► failed
                       ├──refused as it is──► (deleted)  │ reconcile(): found
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
| `failed` | `STATE_FAILED` | failed on Polkurier's side (down, busy, an internal error), or found not to exist; safe to send again as it is |
| `cancelled` | `STATE_CANCELLED` | cancelled on the account |

"Finished" isn't a state: it's the `is_final` flag on a `created` or `cancelled` shipment, and it
only stops the status refresh.

Shipments are looked up through `Model\Shipment\ShipmentFinder`, which holds every query by order,
Magento shipment, Polkurier number, waybill, cash on delivery status and state, and the batches
the cron jobs take. Lists come oldest first; `latest…()` gives the one recorded last. Load one by
id with `ShipmentRepositoryInterface`; grids and forms keep their own collections.

## The draft

A shipment starts as a draft (`Api\Data\ShipmentDraftInterface`, `Model\Shipment\Draft`):
everything the merchant can decide about it. Carrier, description, sender,
recipient and pickup point, parcels (`Api\Data\ShipmentDraft\PackInterface`), cash on delivery,
insurance, cover address, services, handover (courier, drop-off or a later collective pickup, with
an optional date and time window) and carrier-specific fields.

The shipment type isn't chosen: it's what the parcels are. Each parcel's type is a standard,
non-standard or long parcel, an envelope (`ENV`), or a pallet by its size: `VP` 60 × 40, `PPAL`
80 × 60, the EURO pallet 120 × 80 (`EURO`, and `EURO_NST` non-standard), `EUR2` 120 × 100, `PAL`
120 × 120, `PA15` 150 × 150. `Model\Shipment\Placement\ParcelTypeMapper` turns that into Polkurier's two fields: the
order's `shipmenttype` and each parcel's `type`. An envelope goes as `ST`; under a pallet shipment
Polkurier reads `ST` and `NST` as the EURO pallet and its non-standard variant, which is why those
two have codes of their own. A pallet of its own size, made by automatic packing, is the smallest
that takes its footprint (`ParcelTypeMapper::palletFor()`). `getShipmentType()` of a draft with parcels says
what they are; without parcels, it's the type the draft was given, which automatic packing keeps
to. Parcels of more than one kind are refused by `CreateOrderMessageMapper`, and a type the carrier
doesn't take (`available_carriers`' `additional_data.shipmenttype`) by `PlacementService`, since Polkurier
itself doesn't check it.

The module ships only orders placed with a Polkurier method, and leaves every other order to its
own carrier. Orders are loaded through `Model\Shipment\OrderLoader`: `getForPolkurier()` for
anything that starts a new shipment (the draft, placing, pricing, packing and collection times),
which refuses other carriers' orders, and `findForPolkurier()` where such an order is simply left
alone. `get()` and `find()` load any order, for what follows from a shipment already made:
existing Polkurier orders can still be refreshed, reconciled or cancelled.

`Model\Shipment\Draft\DefaultDraftBuilder::createFromOrder()` builds the default draft from the order,
the stored pickup point and the module's settings, narrowed to what the carrier supports.
`ShipmentManagementInterface::getDefaultDraft()` exposes it. `Model\Shipment\Draft\DraftHydrator`
converts between drafts and the flat arrays forms work with.

Orders can ship in parts, so the amounts come from `Model\Shipment\Valuation\OrderAmountCalculator`:

- Cash on delivery is `getCodRemaining()`: the grand total less the `cod_amount` of the order's
  Polkurier shipments in `pending`, `created` or `unknown` (an unconfirmed one may exist). When
  nothing is left, the draft has no cash on delivery.
- Insurance is set when `shipment_defaults/insure_by_default` is on, or the carrier requires
  insurance with cash on delivery: for the cash on delivery amount when there is one, otherwise
  for `getShipmentValue()`, so the parts of an order add up to its total either way. The shipment
  value is the sum of each shipped item's row total with tax, less its discount, per unit, times
  the quantity shipped. Items that ship as part of a parent (`isDummy(true)`) and virtual items
  are left out. When the quantities cover the whole order (ordered less cancelled), or match none
  of its items, it's the grand total.

`createFromOrder()` takes the quantities as `$qtys` (by order item id). `null` means everything the
order still has to ship, which is what the shipment screen sends. The native label flow passes the
Magento shipment's items.

A draft without a sender address is sent from the store: `Model\Shipment\Placement\CreateOrderMessageMapper`
takes **Store Information** (`general/store_information/*`) when
`sender/use_store_information_address` is on, then the shipping origin (`shipping/origin/*`)
when `sender/use_store_origin_address` is on, each only if complete
(`Model\Shipment\Placement\SenderMapper::fromStoreInformation()`, `fromStoreOrigin()`). With neither, the
shipment is refused before anything is sent.

`Model\Shipment\Placement\CreateOrderMessageMapper` turns a draft into the SDK's `CreateOrderMessage`. The SDK
validates the message before anything is sent, so an incomplete draft fails with field-level
errors without reaching Polkurier. The other mappers in `Model\Mapper` do the same for addresses,
bank accounts and templates, and `StreetParser` splits Magento's street lines into street, house
number and flat number. Mappers only map: they don't call Polkurier or read the database beyond
what they're given.

## Placing

`Model\Shipment\Placement\PlacementService` holds the flow; the steps are its collaborators. `PlacementGuard`
refuses when an earlier attempt for the same order is `unknown`, or `pending` and recent. It checks
under the order's lock (see [One change at a time](#one-change-at-a-time)), held until the new
attempt is saved, so two requests at once (a double click, two tabs) can't both pass.
`DraftValidator` settles the sender point, validates the order as the SDK would, and checks that the
carrier takes the shipment's type. `AttemptRecorder` then saves a `polkurier_shipment` row in state
`pending`, together with the serialized request, before anything is sent, so an interrupted
request still leaves a trace, and records what came of it. What happens next depends on the
answer:

| Outcome | State | What happens |
|---|---|---|
| Accepted | `created` | Polkurier number, waybills, price and tracking link stored; order comment; Magento shipment with tracks; event |
| Refused as it is | none | nothing of the attempt is kept, and the reason is thrown as a `LocalizedException`: sent again unchanged it would get the same answer (`ErrorMessageResolver::isTransient()` false) |
| Failed on Polkurier's side | `failed` | the reason and the draft are stored, and the reason thrown: down, busy or an internal error (`isTransient()`), so it can be sent again as it is |
| No clear answer | `unknown` | an order comment; nothing else until it's reconciled |

A shipment is an object, not a log of attempts: an order's `failed` shipment is the row the order's
next attempt uses (`AttemptRecorder::notPlaced()`), whether from **Send again** or not, so there's at most
one per order. A refused attempt leaves that row as it was; one that fails on Polkurier's side
again updates its draft and reason.

"No clear answer" means a connection error, an unreadable answer, or an error that doesn't say the
order was refused. In those cases the module can't know whether the shipment exists, and sending it
again could create a duplicate.

`PlacementService::forNativeShipment()` is the same without shipping the Magento order, for Magento's own
label flow, which creates the shipment itself. It throws on an unclear answer instead of returning the
`unknown` shipment. See [Shipping labels in Magento](magento-labels.md).

### The Magento shipment

The draft's `items` (`Api\Data\ShipmentDraft\ItemInterface`: `order_item_id`, `qty`) say what the
Magento shipment carries. Leave them empty to ship everything the order has left.
`NativeShipmentService::getQuantities()` checks them in `place()` before anything is sent. Every item must
be one the order ships (see `OrderAmountCalculator::getItems()`), no quantity may be more than is left, and
at least one must be above 0. It skips the check when the order has nothing left to ship. The
quantities are stored in `polkurier_shipment.ship_items`, so a Magento shipment made later (reconcile, adopt,
a status refresh, `shipOrder()`) carries the same ones. The native label flow stores the Magento
shipment's quantities there too.

`Model\Shipment\Native\NativeShipmentService::ship()` ships those quantities, or whatever is left to ship on the
order, through Magento's `ShipOrderInterface`, with one track per waybill, titled with the order's shipping
description and carrying the carrier code `polkurier`. The customer is notified if
`labels/notify_customer` is on.

A Magento shipment has at most one standing Polkurier order, and keeps the cancelled ones it had.
The admin never links them; `NativeShipmentService::getTarget()` decides in `place()`, before anything
is sent:

- The draft's `target_shipment_id`, set when the screen is opened from a Magento shipment's page
  (**Add a Polkurier order**, `shipment_id`): that shipment, for its items, once
  `assertFree()` confirms no `pending`, `created` or `unknown` Polkurier order holds it.
- With items left to ship, a new Magento shipment of the chosen quantities.
- With nothing left, the order's latest Magento shipment that no such Polkurier order holds
  (`getFreeShipments()`): one created without a label, or one whose Polkurier order was cancelled.
- With neither, the shipment is refused: every Magento shipment already has its Polkurier order.

The target is stored in `target_shipment_id` of the Polkurier shipment. A Magento shipment keeps
every Polkurier order it has had (`polkurier_shipment.shipment_id` is indexed, not unique), at
most one of them standing, so no item is shipped twice: Magento ships each item once, a new
Magento shipment takes only what's left (`getQuantities()`), a Magento shipment takes a new
Polkurier order only when free, and the parcels hold no more than the shipment carries
(`getPackages()`, and `NativeLabelService` for Magento's packaging window). The native label flow
checks the same in `PlacementService::forNativeShipment()`, with the Magento shipment when it has one.

The draft's parcels can say what each holds (`PackInterface::getItems()`, the same `ItemInterface`
rows). Every parcel is one parcel (`amount` is always 1). `getPackages()` checks that the parcels
hold no more of an item than the shipment carries, and turns them into the Magento shipment's
packages in the form Magento's packaging window saves them: `params` (container, size, weight in
kilograms, customs value) and `items` by order item id (name, weight, price, quantity). They're
stored in `polkurier_shipment.ship_packages` and written to the new Magento shipment's `packages`,
so **Show Packages** works as for a label from Magento's page. The module writes the field as the
plain arrays Magento itself stores, since that's what Magento reads, although
`ShipmentInterface::setPackages()` is typed for package objects. Item weights come from the order
items, converted to kilograms from the store's weight unit (`Model\Carrier\WeightUnitConverter`, which uses
Magento's own conversion, as its carriers do: `Shipping\Helper\Carrier::convertMeasureWeight()`).

`Model\Shipment\Packing\AutoPacker` packs a shipment's items into parcels. It uses the products' shipping
dimensions (`polkurier_length`, `polkurier_width`, `polkurier_height`, decimal centimetres, added by
`Setup\Patch\Data\AddProductDimensionsPatch` in a "Shipping dimensions" group, admin only and removed
when the patch is reverted), read through `Model\Shipment\Packing\ItemDimensionsProvider`. For the option of a
configurable product it uses the option's product, falling back to the parent's dimensions. A
bundle shipped together uses its own dimensions, or else a box around its parts stacked lying
flat (the largest part's length and width, the sum of their heights per bundle), and stays
unmeasured if any part has no dimensions. The templates are the pack templates with a size.

The packing aims for the fewest parcels, and only uses templates of one shipment type: the
draft's when it has parcels (`pack()`), otherwise the one given or `shipment_defaults/default_shipment_type`
(`packByItems()`). Units no template of the type takes go in packs of their own size of the type:
parcels (`ST`), envelopes (`ENV`), or the smallest pallet they stand on.
`fill()` builds the parcels one at a time. If the smallest template that takes every unit left
exists, it's the last parcel. Otherwise the template that packs the most volume of what's left
makes the next parcel, the smaller one on a tie. A unit that fits no template gets a parcel of its
own size. `merge()` then puts any two parcels whose units fit one template together, until none do.
Finally each parcel takes the smallest template its units fit.

Whether units fit is worked out with guillotine cuts. A box's empty space is a list of disjoint
boxes. Units go in biggest by volume first, each into the empty box it fills best. It's turned the
one of six ways that leaves the smallest gap against a side. What's left of that box is cut into up
to three boxes beside the unit, the longest leftover first, across the whole box. Units never
overlap, and a gap too narrow for a unit never counts as room. `packing/packing_margin` centimetres
are added to each unit's sides first. Guillotine cuts miss some arrangements, so the result can
have a bigger box, or one parcel more, than the best one; a parcel is never overfull. Each parcel
weighs `ParcelWeightEnum::weigh()` of its template and contents (`packing/parcel_weight`; the
template's weight when the products have none). With `packing/weight_limit` set to `keep_under`, a
template's weight is the most its parcel takes, so a heavy load gets more parcels or a bigger
template; with `warn` it's ignored while packing and only pointed out on the screen. It returns
nothing, with the reason, when an item has no dimensions or no template has a size.

`DefaultDraftBuilder` uses it for the default parcels, falling back to one parcel of the default
template. **Pack automatically** on the shipment screen (`Controller\Adminhtml\Shipment\Pack`)
goes through `AutoPacker::packDraft()`, which takes what the draft ships (the chosen items,
what the order has left, or what its Magento shipment carries), keeps to the parcels' kind, and
says which products' weights the parcels leave out.

Going on an existing shipment, `release()` unlinks the cancelled order there and deletes its
waybills from the shipment's tracks, then the new waybills are added. The native label flow does
the same in `Observer\LinkLabelledShipmentObserver` when a label is created again for a shipment whose
Polkurier order was cancelled.

The Polkurier order exists whatever happens there, so a failure is recorded in `last_error`
rather than thrown. The shipment stays `created` with no `shipment_id`, every status refresh tries
again once the attempt is `ShipmentAgeChecker::SHIP_AGAIN_AFTER_MINUTES` (5) old, and `shipOrder()` does
it on demand. A younger one is left alone: the request that placed it may still be making its
Magento shipment, which the native label flow links only when Magento saves it.

### The order's shipping

`Model\Shipment\Native\ActualShippingUpdater` keeps the order's `shipping_method`, `shipping_description` and
pickup point (`polkurier_order_point`) what its latest `created` Polkurier order went with. It runs
from `Observer\FollowShipmentCarrierObserver` on `polkurier_shipment_save_after` whenever a shipment
becomes or stops being `created`. The first change keeps the customer's choice in
`polkurier_order_shipping_choice` (`ShippingChoiceRepository`), exposed as the order's
`polkurier_shipping_choice` extension attribute; with no `created` shipment left it's put back
and forgotten. The description is written in the order store's language, through
`PointDescriptionBuilder`. Only the two shipping fields of the order are saved
(`Magento\Sales\Model\ResourceModel\Attribute`), on the order repository's instance, which a
page saving the order afterwards holds too, and the order grid row is refreshed. Each change adds
a comment to the order's history.

## Settling what's undecided

`Cron\ReconcileShipmentsTask`, every 10 minutes, settles what's undecided:
`Model\Shipment\Placement\FailedShipmentManager` removes `failed` shipments 48 hours (`LIFETIME_HOURS`) after their last attempt (`created_at`,
renewed by each attempt) and **Discard** removes one at once, each noted on the order;
`ReconciliationService::reconcilePending()` looks for unanswered ones; and `Model\Shipment\AttentionNotifier` adds a major
admin notification (`NotifierInterface`, Magento's inbox) for each shipment still needing someone:
`failed` for 24 hours (`FailedShipmentManager::NOTICE_AFTER_HOURS`), `pending`/`unknown` or `created` without
a Magento shipment for an hour (`SETTLE_HOURS`). `polkurier_shipment.attention_notified_at` makes
it once per shipment; `PlacementService` clears it with a new attempt. The notification links to the
details page without a URL key (made by the cron, outside any session), so that action is public
(`$_publicActions`); the ACL still applies.

## Reconciliation

Every order is sent with the shop's own ids: `external_application_id` is the installation's
(`Sdk\ApplicationIdProvider`: `general/application_id` if entered, else `magento2-` and 12 hex digits from
the first encryption key, which a staging copy sharing the key would share too),
`external_order_id` the Magento shipment's number or the order's (`general/external_order_id`), and
`external_order_number` the order's. A new Magento shipment's number is taken from Magento's
sequence before placing (`Placement\ExternalIdService`), kept as `reserved_shipment_number`, and given to
the shipment once made: by `NativeLabelService` to the shipment Magento is saving, and through
`ReservedNumberRegistry` and a plugin on `ShipmentDocumentFactory` to one the module ships. An
attempt sent again keeps its number. Polkurier stores the ids as given (up to 50 characters, not
unique, never checked) and may fail to keep them without saying so.

`reconcile()` refuses a `pending` attempt younger than `ShipmentAgeChecker::ANSWER_WAIT_MINUTES` (2),
whose request may still get its answer. Otherwise it asks `Model\Shipment\Status\RemoteOrderMatcher` for orders on the account that match the recorded
attempt. It first lists the orders with the attempt's ids; failing that, it looks as for an order
sent without them: the same carrier, placed from the day before to the day after (Polkurier
filters orders by date only). Either way an order must have the same description, recipient
postcode, phone and number of parcels, and not be held by another shipment.

- **One or more matches**: the earliest one not cancelled is adopted, and the shipment is finished
  as if it had been accepted: Magento shipment, event. Any other live ones are reported in an
  order comment and in `last_error` as probable duplicates. A description such as the order
  number is no unique key: an order shipped again after a cancellation has two orders with it.
- **Only cancelled orders match**: the earliest is linked as `cancelled`, so that it isn't found
  again, without a Magento shipment or event; the order can be shipped again. Linking one by
  hand does the same.
- **Only orders listed without their parcels match**: they may be it, but can't be told apart well
  enough. The shipment stays `unknown` with their numbers in `last_error`, to be linked by hand, and
  is never taken as not placed.
- **No match**: once the attempt is `ShipmentAgeChecker::NOT_PLACED_AFTER_MINUTES` (15) old, the
  shipment becomes `failed`, which makes it safe to send again. Until then it stays `unknown`,
  because the order may not be listed yet.

`Cron\ReconcileShipmentsTask` runs this every 10 minutes (`ReconciliationService::reconcilePending()`), for
`unknown` and `pending` shipments whose attempt is more than
`ShipmentAgeChecker::RECONCILE_PENDING_AFTER_MINUTES` (5) minutes old. A `pending` shipment that old was cut off
mid-request. `adopt()` links a shipment to a Polkurier order number given by the caller, for
integrations that know it.

## Following a shipment

`refreshStatus()` reads the order from the account and applies it to the shipment through
`Model\Shipment\Status\RemoteOrderApplier`: waybills, delivery status and date, cash on delivery status and
price, and a full local copy of the order (`details`, built by `RemoteOrderApplier::fromOrder()`:
addresses, parcels, cash on delivery, pickup, services and the cost items). New waybills become
new tracks on the Magento shipment. A `created` shipment without a Magento shipment gets another
try at one.

The carrier's scans are read separately, since asking for them makes the call slower:
`Model\Shipment\Details\DetailsLoader` reads them for the details page when they're missing, or older than
an hour for a shipment still on its way, and the manual **Update status** reads them too. The scans
are stored on their own (`ResourceModel\Shipment::updateColumns()`), so opening the page never
writes the rest of the row. A missing order copy is read for the page only: storing it, and acting
on it, is left to `refreshStatus()`, from the cron or **Update status**.

`ViewModel\Adminhtml\ShipmentDetails` turns the record, the copy (or, before Polkurier has been
read, the stored request, which `Model\Shipment\Details\SentOrderReader` reads into the same shape) and
the scans into the page's sections, with links to the Magento order and shipment.
`Details\OptionLabelProvider` names the codes, and `Details\Formatter` writes the values. Times Polkurier
reports are in Polish time (`Model\Sdk\PolkurierTimeZone::ZONE`). The status and delivery times are
stored in UTC, like every time the module stores, so grids and their filters convert them as
Magento's own; the scans are kept as reported. All are shown in the admin's time zone, so the times
on the page agree.

`RemoteOrderApplier::isFinal()` decides when a shipment is finished:

- cancelled;
- delivered, with no cash on delivery, or with it paid out, settled or offset against a
  compensation;
- returning to the sender with no change for 14 days, since returns don't get a final status of
  their own.

`Status\StatusRefreshService::refreshPending()`, run by `Cron\RefreshShipmentStatusTask` on the schedule of
`carriers/polkurier/sync/status_schedule` (a cron expression, every 30 minutes by default; the job's
`config_path` in `etc/crontab.xml`), refreshes `created` shipments of every store that aren't final
and weren't checked since the previous run (`ConfigProvider::getStatusIntervalMinutes()`, less a minute),
least recently checked first, up to `StatusRefreshService::BATCH_SIZE` (200) per run; the job runs it
only when the status sync is on. A shipment that fails to refresh is marked as checked too, so one bad shipment can't
hold up the rest. `refreshNow()`, behind **Update statuses** on the shipments grid
(`Controller\Adminhtml\Shipment\RefreshAll`), takes the same shipments however recently they were
checked, also with the status sync off. The batches call the services' own `refresh()` and
`reconcile()`, not `ShipmentManagementInterface`: a plugin on the service contract sees what an
admin or integration asks for, not each shipment of a scheduled run.

Polkurier's status webhook (`Controller\Webhook\Shipment\Update`, at
`polkurier/webhook/shipment_update`, POST only, no form key) hands each event to
`Model\Shipment\Status\StatusWebhookHandler::receive()`. The event is form-encoded `type` (CREATED, UPDATED,
CANCELLED), `data` (the order as `get_orders` gives it) and `hash`, an HMAC-SHA256 of the
form-encoded `type` and `data` keyed with the secret the panel shows (`sync/webhook_secret`). The signature is checked on the raw body, whose last field is
`hash`, so PHP's `max_input_vars` never cuts the fields it covers. Of the event only
`data[number]` is read: the `created` shipment with that Polkurier number, if there is one, goes
through `refreshStatus()`, which reads the order again. Polkurier sends an event once, without
retries, in no set order and without a time or nonce, and its staff cancel with UPDATED rather
than CANCELLED; reading the order again makes a repeated, late or replayed event harmless and
catches every cancellation. Polkurier counts an event as delivered when the body is exactly `OK`:
the action answers `OK` once handled, 403 for a missing secret or a bad signature, and 503 when
the refresh failed (logged; the schedule still follows the shipment).

## Other operations

- `cancel()` cancels a `created` shipment on the account, then clears its Magento shipment
  (`NativeFollowUpService::clearShipment()`): the shipment and its items
  stay, linked to the cancelled order, but its `shipping_label`, its `packages` and the tracks of
  the cancelled order's waybills go, so Magento offers **Create Shipping Label** again and another
  Polkurier order can go on it. A shipment another standing Polkurier order holds, or of another
  order, is left alone. A failure is logged and the cancellation stands. `StatusRefreshService::refresh()`
  does the same when a refresh finds the order cancelled in the Polkurier panel. The comment on the
  order names the shipment cleared.
- `Model\Shipment\DocumentService` fetches labels and handover protocols for one or more shipments, in
  one document. With more than one, it first runs `StatusRefreshService::refresh()` on each and leaves
  out those no longer placed, returning why under `left_out`: Polkurier drops labels it can't
  print from a multi-order file without saying so, and fails a whole protocol over one number it
  doesn't know.
- `Model\Shipment\PickupService` loads the collection windows for a sender, carrier and date, and books
  collective pickups for shipments placed with `pickup_mode = multi`; `selectAwaiting()` picks
  those of a grid selection that await one.
- `Model\Shipment\Valuation\CostEstimator` prices a draft before it's placed: `quoteAll()` with every
  carrier that can take it, in one request. The shipment
  screen uses it for its live prices. A price, not a dry run: a draft that prices cleanly can still
  be refused. `CostEstimator` reads the answer, whose shape the SDK leaves open:
  the list Polkurier sends (`Test/Unit/_files/order_valuation_v2.json` is a recorded one), the
  quotes keyed by carrier the SDK documents, or a single quote. Entries it can't read are logged.
  Prices come from `order_valuation_v2` only, which builds the order as `create_order` does. The
  older `order_valuation` prices some parcels differently from what is charged, and below it,
  offers carriers that then refuse the parcel, and ignores a payout to the money box: don't call
  it, here or in anything quoting alongside this module.

## One change at a time

`Model\Shipment\OrderLock` (`polkurier_order_<order id>`, Magento's lock manager, re-entrant
within a request) lets one request at a time change an order's Polkurier shipments: placing
(while the attempt is recorded, and again while the accepted order is saved and shipped in
Magento), `reconcile()`, `adopt()`, `refreshStatus()`, `shipOrder()` and `cancel()`. Without it,
the scheduled reconciliation and the admin's, or a webhook and the scheduled update, could both
make a Magento shipment for the same Polkurier order, or both comment on it. Polkurier is asked
before the lock is taken, and the shipment is read again under it: one settled meanwhile is left
as it is, or refused when linked by hand. A request that waits more than 10 seconds
(`TIMEOUT_SECONDS`) for it fails with a message to try again; the cron jobs log it and leave the
shipment to their next run.


## Entries typed in for a shipment

A sender or a cash on delivery account can be typed in rather than chosen: the screens send
`sender_address_id` or `cod_bank_account_id` as `new` (`ShipmentDraftInterface::NEW`), with the
fields flat beside the recipient's (`new_sender_*` by `NEW_SENDER_FIELDS`, `new_bank_account_number`).
`DraftHydrator` turns `new` into `isNewSender()` / `isNewBankAccount()` and back.
`CreateOrderMessageMapper` sends them as typed (`SenderMapper::fromFields()`; the account number
without spaces), and refuses a typed-in sender missing what Polkurier needs. A parcel without a
template carries `keep_as`, the pack template name to save it under; Magento's packaging window
adds it to a package's params as `polkurier_keep_as` (`Native\NativeLabelPackageMapper::KEEP_AS_PARAM`).

`keep_new_sender`, `keep_new_bank_account` and `keep_as` ask for them to be saved for later. The
shipment screen's `Save` action drops what the admin isn't allowed to change (the Address, Bank
account and Pack template `Save` actions' `ADMIN_RESOURCE`), with a warning, and so does
`Plugin\Adminhtml\NativeLabelPagePlugin` for pack templates on Magento's shipment page.
`PlacementService` calls `ManualEntrySaver::keep()` once Polkurier has accepted the order, which
saves each through the settings pages' handlers (`AddressHandler`, `BankAccountHandler`,
`PackTemplateHandler`), so they're pushed to Polkurier the same way. What became of them is gathered
in one `FlashMessageCollector` that the action placing the shipment sends after it
(`ManualEntrySaver::takeOutcome()`: the shipment screen's `Save`, and `NativeLabelPagePlugin` on
Magento's). A save that fails never undoes the shipment. `DefaultDraftBuilder` starts with a
typed-in sender when there's no address and no store address to fall back on, and with a typed-in
account when cash on delivery has no account to go to, both to be kept.

A pack template's `tare` (Magento only; not in Polkurier's pack templates, so never synced) is
added to what a parcel holds when parcels weigh their contents (`ParcelWeightEnum::weigh()`,
`rules.parcelWeight()`), and taken off the maximum weight the packer fills a template up to.

Next: [Shipping labels in Magento](magento-labels.md)
