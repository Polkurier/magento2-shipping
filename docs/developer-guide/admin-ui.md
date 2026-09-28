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
| Orders | `polkurier_order_listing` | `polkurier/order/index` |
| Shipments | `polkurier_shipment_listing` | `polkurier/shipment/index` |
| Store shipments | `polkurier_sales_shipment_listing` | `polkurier/salesshipment/index` |
| Shipment screen | `polkurier_shipment_form` | `polkurier/shipment/create` |
| Collective pickup | `polkurier_shipment_pickup_form` | `polkurier/shipment/pickupForm` |
| Cash on delivery payouts | `polkurier_payout_listing`, `polkurier_payout_form` | `polkurier/moneybox/index`, `polkurier/moneybox/payout` |
| Carriers | `polkurier_carrier_listing`, `polkurier_carrier_form` | `polkurier/carrier/*` |
| Addresses | `polkurier_address_listing`, `polkurier_address_form` | `polkurier/address/*` |
| Bank accounts | `polkurier_bank_account_listing`, `polkurier_bank_account_form` | `polkurier/bankaccount/*` |
| Pack templates | `polkurier_pack_template_listing`, `polkurier_pack_template_form` | `polkurier/packtemplate/*` |
| Order page, Information tab | `polkurier_order_point_form`, `polkurier_order_shipment_listing` | `sales_order_view` layout |

Two things aren't UI components, because they sit inside pages Magento still builds from blocks:
the *Test connection* button in the system configuration (`Block\Adminhtml\System\Config\TestConnection`),
and the label options on Magento's New Shipment page (`Block\Adminhtml\Shipment\LabelOptions`, see
[Shipping labels in Magento](magento-labels.md)).

Data providers, columns and buttons live in `Ui/`. `Model\Shipment\ShipmentOptions` supplies the
carrier, sender and bank account choices to the shipment screen and the New Shipment page.

## The shipment screen

The screen is `polkurier_shipment_form`, laid out as three columns inside a `workspace` container:
the carrier list, the shipment (cards and the parcels), and a summary. The details behind each
card live in form modals (`recipient_modal`, `shipment_modal`, `handover_modal`, `cod_modal`,
`extras_modal`, `fields_modal`). Modals are containers, so their fields keep the form's data scope
and are submitted and validated with it; **Done** is the modal's `actionDone` (validate, then
close) and **Cancel** its `actionCancel` (restore the values from before it opened).

`Ui\Component\Form\Shipment\DataProvider` fills the form with the default draft (or, after a
refused attempt, what the merchant entered, from the data persistor), plus the order's currency and
total, what each carrier supports, labels for every choice (`data.choices`, for the cards) and the
map configuration. Its `getMeta()` injects the select options by component path, so moving a field
means updating that path too.

The client side lives in `view/adminhtml/web/js/shipment/`:

| Script | |
|---|---|
| `workspace/carrier-picker.js` | the carrier list: extends `carrier.js`, shows every carrier with its quote, cheapest first |
| `carrier.js` | publishes what the chosen carrier supports as `data.features`, which the rest of the form imports |
| `workspace/card.js` | a card: summary lines and issues for one group, opens its modal |
| `workspace/summary.js` | the side panel: price, cheaper alternative, issues, placing; asks for quotes |
| `workspace/rules.js` | the checks shared by cards and summary (missing point, date, parcel sizes and so on) |
| `services.js` | narrows the services to the carrier's |
| `additional-fields.js` | renders carrier-specific fields |
| `pack-template.js` | fills a parcel row from a template |
| `point.js` | the pickup point field with the map |
| `points-map.js` | opens the pickup point map in an iframe of its own (see below); shared by `point.js` and `label-options.js` |
| `pickup-field.js`, `pickup-times.js` | the handover fields and the collection windows |
| `label-options.js` | the New Shipment page (not this screen) |

How the live prices work: `summary.js` listens to the provider's `data` (any field change notifies
it), and a moment after the last change posts the draft fields to
`Controller\Adminhtml\Shipment\Calculate`, unless they're the same as last time. The controller
returns the chosen carrier's price and every carrier's quote from
`Model\Shipment\CostEstimator::quoteAll()`, one request to Polkurier for all of them. The quotes
are published on the provider as `quotes` (outside `data`, so they aren't submitted and don't
trigger another quote), with `quoting` while a request is running; the carrier list and the cards
import them. Only the answer to the latest request is used.

The map widget can't run in the admin page itself: Magento's admin loads Prototype.js, which
replaces native `Array.prototype` methods (`map`, `find`, `reverse` and others), and the widget
overflows the stack there. `points-map.js` therefore writes a blank same-origin iframe over the
page, loads the widget's script and styles into it and creates the widget from the iframe's
window, so it runs with clean built-ins. The iframe is shown only while the map dialog is open
(it hides on `dialog:closed`), and the chosen point's id is passed back to the page. The
storefront has no Prototype and loads the widget directly.

When the form turns out invalid on submit (`params.invalid`), `summary.js` opens the modal that
holds the first field with an error, or scrolls to it on the page.

`Controller\Adminhtml\Shipment\PickupTimes` backs the collection windows. `Save` places the
shipment through `ShipmentManagementInterface::create()`.

Styles are in `view/adminhtml/web/css/shipment-form.css`. Below 1440 px the summary moves under the
other two columns, and below 900 px everything stacks.

## Controllers and actions

Controllers live in `Controller/Adminhtml/<Screen>/`. Every one sets `ADMIN_RESOURCE`, and every
one that changes data, here or on the Polkurier account, implements `HttpPostActionInterface`.
Buttons that trigger such actions from a listing (*Synchronize with Polkurier*, *Refresh carriers
from Polkurier*) are `Ui\Component\Control\PostButton` virtual types in `etc/adminhtml/di.xml`,
posting through `mage/dataPost` with the form key; row actions post the same way.

The carrier form's logo is a file uploader posting to `Controller\Adminhtml\Carrier\UploadLogo`,
which stores the file through `Model\Carrier\CarrierLogo` (raster images only; an SVG could carry
scripts). The file is only linked to the carrier when the form is saved, and `Save` accepts only
paths inside the logo directory. The carriers list is a SearchResult collection of its own
(`Model\ResourceModel\CarrierConfig\Grid\Collection`) that leaves out each website's carriers
outside its *Available carriers*.

`Controller\Adminhtml\MasterData\AbstractSave`, `AbstractDelete`, `AbstractMassDelete`,
`AbstractInlineEdit` and `AbstractSync` serve the three synced screens through their
`Model\Admin\*Handler`.

**Inline editing.** The addresses, bank accounts, pack templates and carriers lists have an
`editorConfig` posting to `<screen>/inlineEdit`. `AbstractInlineEdit` saves each row through the
handler, as the form would, limited to the fields its subclass lists in `getEditableFields()`,
then reloads the entry and reports a sync error as that row's message. The carriers'
`InlineEdit` also takes `price`, which the grid collection exposes for carriers with fixed pricing
(`Model\Pricing\Fixed::PRICE` in `price_info`); for tiered carriers it refuses the change.

**Save and Continue Edit** on the four edit forms is `Ui\Component\Control\SaveAndContinueButton`,
with one virtual type per form in `etc/adminhtml/di.xml`. It saves through the form's own `save`
action with `back=continue`, which the save controllers already honour.

**Magento's records.** *Orders* and *Store shipments* are copies of Magento's `sales_order_grid`
and `sales_order_shipment_grid`, with the collections replaced by
`Model\ResourceModel\SalesOrder\Grid\Collection` and `SalesShipment\Grid\Collection`. Both narrow
Magento's grid tables with `Model\ResourceModel\SalesOrder\PolkurierOrders::select()`: the orders
placed with a `polkurier_*` method, plus those with a Polkurier shipment. The mass actions are
Magento's own controllers; they find the listing through the `namespace` parameter and redirect to
the data provider's `referer_url`, which only the argument-style `dataSource` definition can set,
hence the older format there.

## ACL

Resources are declared in `etc/acl.xml`:

| Resource | |
|---|---|
| `Polkurier_Shipping::root` | the Polkurier menu |
| `Polkurier_Shipping::orders`, `::sales_shipments` | the lists of Magento's orders and shipments; their mass actions also check Magento's resources |
| `Polkurier_Shipping::shipments` | view shipments, labels, protocols; refresh statuses |
| `Polkurier_Shipping::shipment_create` | place, reconcile, adopt and ship; collective pickups; labels on Magento's shipment page |
| `Polkurier_Shipping::shipment_cancel` | cancel shipments |
| `Polkurier_Shipping::money_box` | view payouts |
| `Polkurier_Shipping::money_box_payout` | request and cancel payouts |
| `Polkurier_Shipping::settings` | the Settings menu |
| `Polkurier_Shipping::addresses`, `::bank_accounts`, `::pack_templates`, `::carriers` | each settings screen |
| `Polkurier_Shipping::config` | the configuration section, under `Magento_Config::config` |

## Menu

`etc/adminhtml/menu.xml` adds a **Polkurier** menu with **Sales** (Orders, Shipments, Store
shipments, Cash on delivery payouts) and **Settings** (Carriers, Addresses, Bank accounts, Pack templates,
Configuration). Its icon is `view/adminhtml/web/images/menu-icon.svg`, applied as a CSS mask in
`view/adminhtml/web/css/menu.css`, so it takes the menu's colours.

## Order page

- `view/adminhtml/layout/sales_order_view.xml` adds the pickup point box and the order's
  Polkurier shipments to the Information tab.
- The pickup point box (`polkurier_order_point_form`) is editable for orders placed with a
  Polkurier method, for admins with `Polkurier_Shipping::shipment_create`. **Save pickup point**
  (`view/adminhtml/web/js/order/point-save.js`) posts to `Controller\Adminhtml\Order\SavePoint`,
  which calls `Model\OrderPointEditor::change()`: the code is looked up with Polkurier among the
  carrier's point providers, the label and cash on delivery flag are stored, and the change is
  added to the order's comments. An empty code removes the point unless the carrier requires one.
  **Choose on map** opens `points-map.js` with `data.map` from the form's data provider (map
  token, the carrier's point providers and the shipping postcode and town; left out when there's
  no token or the carrier has no points) and saves the chosen point the same way.
- `Plugin\Adminhtml\OrderViewPlugin` handles the **Ship** and **Ship with Polkurier** buttons.
- `etc/adminhtml/events.xml` disables the checkout's point check (`polkurier_validate_pickup_point`)
  for orders created in the admin, which have no way to pick a point.

Next: [Scheduled jobs](cron.md)
