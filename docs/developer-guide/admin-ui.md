<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Admin UI

[Developer guide](README.md) › Admin UI

## Screens

Every admin screen is a UI component (`view/adminhtml/ui_component`):

| Screen | Component | Route |
|---|---|---|
| Orders | Magento's `sales_order_grid`, with the `shipping_carrier` filter (`Plugin\Adminhtml\OrderGridFilterPlugin`), applied by `js/grid/carrier-filter.js` when opened as `sales/order/index/carrier/polkurier` | `sales/order/index` |
| Shipments | `polkurier_shipment_listing` | `polkurier/shipment/index` |
| Shipment screen | `polkurier_shipment_form` | `polkurier/shipment/create` |
| Shipment details | `polkurier_shipment_view` | `polkurier/shipment/view` |
| Collective pickup | `polkurier_shipment_pickup_form` | `polkurier/shipment/pickupForm` |
| Cash on delivery payouts | `polkurier_payout_listing`, `polkurier_payout_form` | `polkurier/moneybox/index`, `polkurier/moneybox/payout` |
| Carriers | `polkurier_carrier_listing`, `polkurier_carrier_form` | `polkurier/carrier/*` |
| Addresses | `polkurier_address_listing`, `polkurier_address_form` | `polkurier/address/*` |
| Bank accounts | `polkurier_bank_account_listing`, `polkurier_bank_account_form` | `polkurier/bankaccount/*` |
| Pack templates | `polkurier_pack_template_listing`, `polkurier_pack_template_form` | `polkurier/packtemplate/*` |
| Order page, Shipments tab | `sales_order_view_shipment_grid` (Magento's, extended) | |
| Order page, Polkurier tab | `polkurier_order_view_shipment_listing` | in `Block\Adminhtml\Order\View\Tab\PolkurierShipments` |

Two things aren't UI components, because they sit inside pages Magento still builds from blocks:
the *Test connection* button in the system configuration (`Block\Adminhtml\System\Config\TestConnection`),
and the label options on Magento's New Shipment page (a template with the view model
`ViewModel\Adminhtml\LabelOptions`, see [Shipping labels in Magento](magento-labels.md)). What
the label options and the shipment screen start from, the default draft, pack templates, map and
amounts, comes from one place, `Model\Shipment\ScreenContext`, so the two stay alike.

Data providers, columns and buttons live in `Ui/`. `Model\Shipment\ShipmentOptions` supplies the
carrier, sender and bank account choices to the shipment screen and the New Shipment page.

## The shipment screen

The screen is `polkurier_shipment_form`: a `workspace` container with a `main` and a `side` column. `main` holds five sections (`shipment_section` and `items_section` side by side, then
`extras_section`, `sender_section`, `recipient_section`), each a `workspace/section` container rendered as an admin page section with
an optional action that opens a modal. `side` holds the
parcels (`parcels_section`, a section too) and the summary. The details live in form modals (`carrier_modal`, `shipment_modal`,
`sender_modal`, `recipient_modal`, `cod_modal`, `insurance_modal`). Modals are containers, so their
fields keep the form's data scope and are submitted and validated with it; **Done** is the modal's
`actionDone` (validate, then close) and **Cancel** its `actionCancel` (restore the values from
before it opened). The carrier and sender pickers close their modal when something is chosen.

The parts of the sections aren't fields: they extend `workspace/part.js`, read `data` from the
provider, redraw when it or the quotes change, and write back to `data` (for example
`data.packs`, `data.services`, `data.pickup_*`). What they write is submitted with the form like
any field's value. The parcel editor and the template chooser are `Magento_Ui/js/modal/modal`
widgets owned by `workspace/parcels.js`.

`Ui\Component\Form\Shipment\DataProvider` fills the form with the default draft (or, after a
refused attempt, what the merchant entered, which `Controller\Adminhtml\Shipment\Save` keeps in
`Model\Shipment\RejectedDraft`), plus the order's currency and
total, what each carrier supports, the sender addresses (`senders`, and `store_sender` for the
fallback), cover addresses, pack templates, the recipient point's address, Polkurier's carrier
notices (`Model\Carrier\CarrierNotices`, cached for an hour, with their markup narrowed to a
few inline tags), labels for every choice (`data.choices`) and the map configuration. Its
`getMeta()` injects the select options of the modals' fields by component path, so moving a field
means updating that path too.

The client side lives in `view/adminhtml/web/js/shipment/`:

| Script | |
|---|---|
| `workspace/part.js` | the base of the parts below |
| `workspace/shipment-details.js` | carrier, contents and price, with Polkurier's notices about the carrier under it, then what the shipment is worth (`rules.shipmentWorth()`) |
| `workspace/section.js` | a titled section with an optional action and its parts; with `cards`, it lists the provider's `issues` about what it holds (warnings as they come, blocking ones once `validated` is set, marking the section `_invalid`), each with **Fix** where the issue's `fix`, or the section's `fixModals`/`fixParts`, says it's fixed |
| `workspace/items.js` | the items to ship and their quantities (`data.items`), what they're worth (`shipment_value`, by `rules.shipmentWorth()`, the rule of `OrderAmounts::getShipmentWorth()`), and the insurance following it until it's set by hand |
| `workspace/parcels.js` | the parcels list with what each holds, the parcel editor (size, weight and contents; until typed in, the weight follows `parcel_weight` through `rules.parcelWeight()`), which every new parcel opens in, the template chooser, and **Pack automatically** (`urls.pack`, see [Shipments](shipments.md#the-magento-shipment)) |
| `workspace/extras.js` | switches for cash on delivery, insurance, the services any carrier offers for the shipment's type (`rules.servicesFor()`) and the cover address (`COVER_ADDRESS_SENDER`, which isn't sent as a service: the cover address switches it on); a chosen service the carrier doesn't offer is a carrier problem (`rules.servicesLacking()`), and the carrier picker tucks such carriers away. Cash on delivery starts from `cod_remaining`, insurance from `shipment_value` (see [Shipments](shipments.md#the-draft)) |
| `workspace/address.js` | the sender's or the recipient's address |
| `workspace/recipient-point.js` | the recipient's pickup point, with the map; marked when `ScreenContext::findRecipientPoint()` found it gone or closed as the screen opened (`recipient_point_closed`), which `rules.js` also reports as blocking |
| `workspace/handover.js` | how the parcels get to the carrier; reads the collection dates from `Controller\Adminhtml\Shipment\PickupTimes` and takes the first free one; under "dropped off", the drop-off point with the map (`rules.senderPointUsed()`: only dropped-off parcels, or a carrier taking them only at points but not as dropped off, are sent with it, as `Model\Shipment\Placing` does) |
| `workspace/summary.js` | asks for quotes, shows the price and a cheaper carrier; works out the issues (`workspace/rules.js`) once for the screen and publishes them as the provider's `issues`; on the form's `data.validate` it sets `validated`, and with blocking issues sets `params.invalid`, so the form doesn't submit, and brings the first marked section into view |
| `workspace/carrier-picker.js` | the carrier list in its modal: extends `carrier.js`, shows every carrier with its quote, cheapest first |
| `workspace/sender-picker.js` | the sender addresses in their modal |
| `carrier.js` | publishes what the chosen carrier supports as `data.features`, which the rest of the form imports |
| `workspace/rules.js` | the checks the sections mark (missing point, date, parcel sizes and so on) |
| `workspace/provider.js` | the form's data provider: placing posts only the draft (`rules.draft()`), as the screen's own data (carriers, choices, templates) would take the request past PHP's `max_input_vars` |
| `additional-fields.js` | renders carrier-specific fields |
| `point.js` | the pickup point field in the recipient modal, with the map |
| `points-map.js` | opens the pickup point map in an iframe of its own (see below); shared with `label-options.js` |
| `label-options.js` | the New Shipment page (not this screen) |

How the live prices work: `summary.js` listens to the provider's `data` (any change notifies it),
and a moment after the last change posts the draft fields to
`Controller\Adminhtml\Shipment\Calculate`, unless they're the same as last time. The controller
returns the chosen carrier's price and every carrier's quote from
`Model\Shipment\CostEstimator::quoteAll()`, one request to Polkurier for all of them. The quotes
are published on the provider as `quotes` (outside `data`, so they aren't submitted and don't
trigger another quote), with `quoting` while a request is running and `quoteMessage` when there's
no price; the carrier list and the shipment section import them. Only the answer to the latest
request is used.

The map widget can't run in the admin page itself: Magento's admin loads Prototype.js, which
replaces native `Array.prototype` methods (`map`, `find`, `reverse` and others), and the widget
overflows the stack there. `points-map.js` therefore writes a blank same-origin iframe over the
page, loads the widget's script and styles into it and creates the widget from the iframe's
window, so it runs with clean built-ins. The iframe is shown only while the map dialog is open
(it hides on `dialog:closed`), and the chosen point's id and the point itself are passed back to
the page. The
storefront has no Prototype and loads the widget directly.

When the form turns out invalid on submit (`params.invalid`), `summary.js` opens the modal that
holds the first field with an error, or scrolls to it on the page.

`Controller\Adminhtml\Shipment\PickupTimes` backs the collection windows. `Save` places the
shipment through `ShipmentManagementInterface::create()`.

Styles are LESS, in `view/adminhtml/web/css/source/_module.less`, which Magento's admin theme
compiles into its own styles, and its partials in `source/module/` (this screen's is
`_shipment-form.less`). They use the theme's colour variables. They only lay the admin's own page
sections, switches, messages and buttons out in two columns; below 1200 px the side column moves
under the form, and below 768 px each section's parts stack.

## Controllers and actions

Controllers live in `Controller/Adminhtml/<Screen>/`. Every one sets `ADMIN_RESOURCE`, and every
one that changes data, here or on the Polkurier account, implements `HttpPostActionInterface`.
Afterwards they go back to the page the action was started from (`Controller\Adminhtml\ReturnsBack`:
the referer, or the `uenc` parameter a form opened on the way carried, as the collective pickup
form does), falling back to the order or the grid when there's none or the action removed that
page.
Buttons that trigger such actions from a listing (*Synchronize with Polkurier*, *Update
statuses*) are `Ui\Component\Control\PostButton` virtual types in `etc/adminhtml/di.xml`,
posting through `mage/dataPost` with the form key; row actions post the same way. So does the
Delete button of the address and pack template forms, a `Ui\Component\Control\DeleteButton` virtual
type confirmed with the same text as the list's row action (`deleteMessage` of `EditActionsColumn`); the
bank account form has its own, which sends the PIN typed into it.

Scripts that ask an action for an answer without leaving the page (prices, collection dates,
packing, the pickup point, payouts, Test connection) post through
`view/adminhtml/web/js/request.js`. It adds the form key and `isAjax`, so an expired session gets
Magento's redirect rather than the login page, and each sender (`request()`) hands on only the
answer to its latest post, so a slow older answer never overwrites a newer one. What a script shows
while waiting, and for an error, stays with the script. The actions they post to extend
`Controller\Adminhtml\AbstractJsonAction`: `answer()` returns the data, sent with `error: false`,
and a `LocalizedException` becomes `{error: true, message}`, with whatever `refused()` adds (such as
`retry`), so every script reads one answer shape.

The carrier form's logo is a file uploader posting to `Controller\Adminhtml\Carrier\UploadLogo`,
which stores the file through `Model\Carrier\CarrierLogo` (raster images only; an SVG could carry
scripts). The file is only linked to the carrier when the form is saved, and `Save` accepts only
paths inside the logo directory. The carriers list is a SearchResult collection of its own
(`Model\ResourceModel\CarrierConfig\Grid\Collection`) that leaves out the carriers the account no
longer offers (`available = 0`) and those outside the *Available carriers*, as
`Model\Carrier\AvailableCarriers` gives them.

`Controller\Adminhtml\MasterData\AbstractSave`, `AbstractDelete`, `AbstractMassDelete`,
`AbstractInlineEdit` and `AbstractSync` serve the three synced screens through their handler, a
`Model\Admin\EntityHandlerInterface`: `SyncableEntityHandler` for addresses and pack templates,
which sends each change itself, and `BankAccountHandler`, which sends it only with the merchant's
code or PIN. Each controller declares its own `ADMIN_RESOURCE`. Their lists are
`Model\ResourceModel\Grid\SyncableCollection` virtual types, which leave out entries deleted in
Magento but not yet in Polkurier (`deleted_at` set). A save that fails goes back to the form with
what was typed, through the data persistor; the carrier and payout forms do the same (the payout
form without the code, its token and the PIN).

**Inline editing.** The addresses, bank accounts, pack templates and carriers lists have an
`editorConfig` posting to `<screen>/inlineEdit`. `AbstractInlineEdit` saves each row through the
handler, as the form would, limited to the fields its subclass lists in `getEditableFields()`,
then reloads the entry and reports a sync error as that row's message. The carriers'
`InlineEdit` also takes `price`, which the grid collection exposes for carriers with fixed pricing
(`Model\Pricing\Fixed::PRICE` in `price_info`); for tiered carriers it refuses the change.
The carriers list's one mass action, `Carrier\MassToggle`, flips `active` on each chosen carrier.
The selections column also serves the inline editor, which edits the ticked rows together.

**Carrier order.** The carriers list's first column, `position`, is
`view/adminhtml/web/js/grid/columns/drag-handle.js`: a gripper per row with the markup and look of
Magento's dynamic rows (`draggable-handle` in a `data-grid-draggable-row-cell`, `_dragged` on the
row, styled in `module/_grid.less` with the theme's control-table variables). Grabbing it lifts a
copy of the row, which follows the pointer, while a bar across the table marks the gap it will land
in. The gap comes from the row middles measured when the row is lifted, and is worked out again
from where the pointer is let go, so a fast move lands where it ends. The page scrolls near the
window's edges, and Escape puts the row back. The column has no caption and doesn't sort, and it
moves itself back to the first place whenever a saved view or column dragging puts it elsewhere. No
column of the list sorts (`childDefaults` `sortable` false), and the grid collection ignores
sorting and always orders by `active` (enabled first), then `sort_order`, then name, so a view
saved sorted by a column can't show another order. The column's `groupBy` (`active`) keeps a
dragged row within its block: the gaps it can land in stop at the block's first and last rows.
The handle is focusable, and Alt+Up / Alt+Down on it move the row one place within its block,
the same way as a drop. A drop reorders the provider's `data.items`, so the grid redraws at once, clears the provider's
cached responses (they'd bring the old order back when a filter is cleared), and posts the shown
ids in order to `polkurier/carrier/arrange`. `Model\Carrier\CarrierOrder::arrange()` puts those
carriers in the places they held among all carriers, so the hidden ones keep theirs, and renumbers
everything by tens. The list shows up to 200 rows a page, so every carrier can be dragged anywhere;
`CarrierConfigSynchronizer` puts new carriers last. Neither the carrier's page nor inline editing
sets `sort_order`.

**Save and Continue Edit** on the four edit forms is `Ui\Component\Control\SaveAndContinueButton`,
with one virtual type per form in `etc/adminhtml/di.xml`. It saves through the form's own `save`
action with `back=continue`, which the save controllers already honour.

**Magento's orders.** *Orders* opens Magento's own `sales_order_grid` at
`sales/order/index/carrier/polkurier`. `view/adminhtml/ui_component/sales_order_grid.xml` adds the
`shipping_carrier` filter, its options every carrier in the store's configuration
(`Model\Config\Source\ShippingCarrier`), enabled or not. `Plugin\Adminhtml\OrderGridFilterPlugin`
applies it to the grid's collection with `Model\ResourceModel\SalesOrder\CarrierOrders::select()`:
the orders whose `sales_order.shipping_method` starts with the carrier's code and an underscore,
found through the index the module adds on that column. `js/grid/carrier-filter.js`, Magento's
`url-filter-applier` given the carrier by the address's `carrier/<code>` path, applies it once the
grid's saved state is restored, so it shows and clears as any other filter.

## ACL

Resources are declared in `etc/acl.xml`:

| Resource | |
|---|---|
| `Polkurier_Shipping::root` | the Polkurier menu |
| `Polkurier_Shipping::sales` | the Sales menu; holds the next four. Its *Orders* item is Magento's orders list, under `Magento_Sales::sales_order` |
| `Polkurier_Shipping::shipments` | view shipments, labels, protocols; refresh statuses |
| `Polkurier_Shipping::shipment_create` | place, reconcile, adopt and ship; collective pickups; labels on Magento's shipment page |
| `Polkurier_Shipping::shipment_cancel` | cancel shipments |
| `Polkurier_Shipping::money_box` | view payouts |
| `Polkurier_Shipping::money_box_payout` | request and cancel payouts |
| `Polkurier_Shipping::settings` | the Settings menu |
| `Polkurier_Shipping::carriers`, `::addresses`, `::bank_accounts`, `::pack_templates` | each settings screen |

Row actions, toolbar buttons and mass actions a role can't run are left out: the shipments list's
**Book a collective pickup** is a `Ui\Component\MassAction\AllowedAction` with an `aclResource`,
and **Request a payout** a `Ui\Component\Control\LinkButton`.

The configuration lives in Magento's `carriers` section, so it has no resource of its own:
the section, the Test connection controller and the Configuration menu item all use
`Magento_Shipping::carriers`.

## Menu

`etc/adminhtml/menu.xml` adds a **Polkurier** menu with **Sales** (Orders, Shipments, Cash on
delivery payouts) and **Settings** (Carriers, Addresses, Bank accounts, Pack templates,
Configuration). Its icon is `view/adminhtml/web/images/menu-icon.svg`, applied as a CSS mask in
`view/adminhtml/web/css/source/module/_menu.less`, so it takes the menu's colours.

## Order page

- The pickup point the customer chose shows under the shipping method in **Shipping & Handling
  Information**, read-only, for methods whose carrier requires a point
  (`CarrierFeatures::isRecipientPointRequired()`; the stored point shows when the carrier list
  can't be had). `sales_order_view.xml` adds `polkurier_order_point`
  (`ViewModel\Adminhtml\OrderPickupPoint`, `templates/order/pickup-point.phtml`) as a child of
  Magento's `order_shipping_view`. That block's template renders no children, so
  `Plugin\Adminhtml\ShippingInfoPlugin` puts the child's HTML in before the section's content
  closes. The point is changed per shipment, on the shipment screen or in the label options.
- `view/adminhtml/ui_component/sales_order_view_shipment_grid.xml` adds a **Polkurier order**
  column to Magento's Shipments tab (`Ui\Component\Listing\Column\PolkurierOrderColumn`): the
  Polkurier order linked to each Magento shipment through `polkurier_shipment.shipment_id`, linked
  to its details page. The column turns off the grid's row click, so a click on the link doesn't
  open the Magento shipment, and it's disabled without `Polkurier_Shipping::shipments`. Its
  `sortOrder="2"` is the position Magento's layout inserts it at, right after the selection column
  and `increment_id`. A column order saved in the grid's bookmark applies over it, as for any
  column; a bookmark saved before the module was installed doesn't know the column, so it shows
  last there until the admin moves it or resets the view.
- The **Polkurier** tab (`Block\Adminhtml\Order\View\Tab\PolkurierShipments`, added after
  `order_shipments` in `sales_order_view.xml`) holds `polkurier_order_view_shipment_listing`: the
  order's Polkurier shipments of every state, filtered by `order_id` like Magento's own tabs, with
  `ShipmentActionsColumn` (without **View order**, through its `onOrderPage` config).
- Every action leaves one message, whatever it did and however many rows it touched:
  `Model\Admin\Flash` gathers what it has to say and sends it once, at the gravest of its kinds.
  Mass actions and synchronizations add their results up (`SyncResult::add()`) rather than
  reporting each row; the master data handlers return the `SyncResultInterface` of their push for
  that.
- `Plugin\Adminhtml\OrderViewPlugin` handles the **Ship** and **Ship with Polkurier** buttons,
  only while the order can ship. `Plugin\Adminhtml\ShipButtonPositionPlugin`, on the toolbar, puts
  **Ship with Polkurier** right after **Ship**: Magento's order page buttons share one sort order,
  and some come in late (Inventory reworks **Ship** in a block of its own), so it renumbers them
  in their order each time the toolbar lays them out.
- `Plugin\Adminhtml\ShipmentViewPlugin` adds **Add a Polkurier order** to the page of a Magento
  shipment without a standing Polkurier order (`MagentoShipment::getFreeShipments()`); the screen
  opens with `shipment_id` and places the order on that shipment (the draft's
  `target_shipment_id`), and **Polkurier order** to the page of one that has a Polkurier order.
- **Send again** (`Model\Shipment\SendAgain`) opens the shipment screen with `retry/<id>`: the
  data provider starts from the draft that attempt kept (`polkurier_shipment.draft`, saved by
  `Placing` with every attempt it keeps: those that failed on Polkurier's side; a refusal of the
  shipment as it is isn't kept), and the page says why it wasn't placed. Offered, in
  `ShipmentActionsColumn` and as `SendAgainButton`, for a failed shipment that is the order's latest
  attempt, while the order can ship or has a Magento shipment without a Polkurier order.
- On an order placed with another carrier's method, none of this appears: there's no point to
  show, the **Polkurier order** column of the Shipments tab disables itself, and
  `OrderViewPlugin` adds no button.
- `etc/adminhtml/events.xml` disables the checkout's point check (`polkurier_validate_pickup_point`)
  for orders created in the admin, which have no way to pick a point.

Next: [Scheduled jobs](cron.md)
