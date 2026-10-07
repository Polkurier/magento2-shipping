<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Shipping labels in Magento

[Developer guide](README.md) › Shipping labels in Magento

Besides its own shipment screen, the module plugs into Magento's own label flow, the one online
carriers such as UPS or DHL use: **Create Shipping Label** on the New Shipment page, the packaging
popup, `Magento\Shipping\Model\Shipping\LabelGenerator` and the label stored on `sales_shipment`.
The aim is to use Magento's mechanism as it is, with as few overrides as possible: no preferences,
only the carrier's own methods, one block, two observers and one plugin, on the Save action (see
below).

## Why `AbstractCarrier`

The carrier stays on `AbstractCarrier`, which already declares every hook the label flow calls.
`AbstractCarrierOnline` adds nothing the module needs, and its `processAdditionalValidation()`
would hide the rates of any cart with a product heavier than a `max_package_weight` the module
doesn't configure.

## The flow

1. **Is it available?** Magento asks the order's carrier `isShippingLabelsAvailable()` before
   showing the checkbox, without saying which order (or store) it's for, so the carrier says yes
   (`Model\Shipment\Native\NativeLabelService::isAvailable()`, which answers from
   `labels/native_labels` only when given a store). `Plugin\Adminhtml\NativeLabelPagePlugin`
   answers for the order instead, after the pages' `canCreateShippingLabel()` (`Create\Items` and
   `View\Form`): no unless `labels/native_labels` is on for the order's store. Only orders placed
   with a Polkurier method get this far, because Magento always asks the order's own carrier.
2. **Packaging popup.** Magento asks the carrier for container types.
   The carrier's `getContainerTypes()` returns the parcel types (`ST`, `NST`, `DLU`, `ENV`, `PPAL`,
   `PAL`), all customizable; they make the shipment's type (see [Shipments](shipments.md#the-draft)),
   and the label options check them when the label is asked for. Pack templates are not container types: they fill in a package from a list of
   their own (see below), so the package's fields stay editable. `isGirthAllowed()` answers no,
   and there are no delivery confirmation or content types.
3. **Label request.** Magento's actions asking for the label (`Order\Shipment\Save` with
   **Create Shipping Label** ticked, and `Order\Shipment\CreateLabel` for a shipment made without)
   check only Magento's own permissions. `NativeLabelPagePlugin`, around both,
   answers the packaging window with an error for an admin not allowed
   `Polkurier_Shipping::shipment_create`, and drops the packages' pack template names, with a
   warning, for one not allowed `Polkurier_Shipping::pack_templates`. Magento then calls the
   carrier's `requestToShipment()` with the shipment and the packages; before that, in Magento's
   `LabelGenerator::create()`, the plugin puts the options posted from the page (the
   `polkurier[...]` fields) on the Magento shipment (`NativeLabelService::FIELD_OPTIONS`), the way Magento
   puts the packages there. The carrier hands it to `NativeLabelService::request()`:
   - it refuses the label when `labels/native_labels` is off for the order's store;
   - it builds the default draft for the order, and applies the options from the Magento shipment,
     limited to `NativeLabelService::OPTION_FIELDS` (the recipient always comes from the order);
   - `Native\NativeLabelPackageMapper` turns the packages into parcels, one per package, converting pounds
     and inches with Magento's own conversion (`Shipping\Helper\Carrier`), and refuses packages
     holding what the shipment doesn't carry;
   - it calls `Model\Shipment\Placement\PlacementService::forNativeShipment()`, which places the order without
     shipping the Magento order, and throws when the outcome is unclear, so Magento doesn't save a
     shipment without tracking. Reconciliation ships the order later;
   - it marks the Magento shipment with the Polkurier shipment's id (`NativeLabelService::FIELD_LINK`).
4. **The answer** is the single entry Magento expects: every waybill as `tracking_number` (Magento
   accepts a list there) and the label as `label_content`. The label is always fetched as PDF,
   whatever label format is configured, because Magento merges labels as PDF documents.
5. **No label yet.** If the label can't be fetched, the answer is empty, the tracks are added to
   the shipment directly, the shipment is flagged (`NativeLabelService::FIELD_NO_LABEL`) and the admin gets a
   notice.
6. **Saving.** Magento saves the shipment and the order in one transaction. Two observers in the
   admin area finish the job:
   - `Observer\PrepareLabelledShipmentObserver` (`sales_order_shipment_save_before`) retitles the tracks
     Magento added with the carrier's admin title (`carriers/polkurier/title`, which customers must
     not see) to the shipping method's name, skips waybills the shipment already tracks, and leaves
     out the label when the shipment is flagged, so no empty document is stored;
   - `Observer\LinkLabelledShipmentObserver` (`sales_order_shipment_save_after`) sets `shipment_id` on the
     Polkurier shipment, in the same transaction. A cancelled Polkurier order the shipment had is
     released first (see [Shipments](shipments.md#the-magento-shipment)), since each Magento
     shipment has one Polkurier order.

A Magento shipment that already has a Polkurier order (one created on the shipment screen, or a
label request whose label wasn't ready) gets that order's label on **Create Shipping Label**, and no
new order is placed.

If Magento fails after the order was placed, the Polkurier shipment stays `created` without a
Magento shipment, and the status refresh creates one later, without a label, like any other
shipment whose Magento shipment is missing.

The `polkurier_api_order_create_after` event fires with `source = "label"`, before Magento saves
its shipment, so `shipment_id` isn't set yet at that point.

## The options on the New Shipment page

The template `shipment/label-options.phtml`, with the view model `ViewModel\Adminhtml\LabelOptions`,
is added to the `extra_shipment_info` container by
`view/adminhtml/layout/adminhtml_order_shipment_new.xml`. That container is inside Magento's
shipment form, so its hidden fields are sent with the label
request: the packaging popup serializes the whole form when it opens. It only renders for
orders placed with a Polkurier method, with native labels on, for admins allowed to place
shipments.

`view/adminhtml/web/js/shipment/label-options.js` ticks Magento's **Create Shipping Label** box
when the page opens (by clicking it, so Magento's own handler runs), hides it, and ticks it again
before every submit: Magento unticks it when the packaging popup is closed, as after a refused
label, and the next submit would save a shipment without a Polkurier order. The server doesn't
rely on that: `Plugin\Adminhtml\ShipmentSavePlugin`, around Magento's
`Order\Shipment\Save`, refuses a submit without the label for an order placed with a Polkurier
method, with the shipment screen or these labels on. It also replaces Magento's "created the
shipping label" message with one saying the label isn't ready, when it wasn't, so the save still
leaves one message.

`label-options.js` shows one summary line per group of options, each edited in place in its line,
one at a time, and keeps the values in the hidden fields. Its parts are in `label-options/`:
`state.js` (the values and the rules they follow), `sections.js` (each group's summary, what's
wrong with it and fields), `editor.js` (a group's fields in its line, with Apply and Cancel; not a
`<form>`, as it sits inside the shipment's, and its fields belong to a form that doesn't exist, so
they're never sent with the shipment) and `packaging.js` (kilograms and centimetres, pack templates, and
packing automatically, in Magento's packaging popup). Packing automatically posts the items the
popup offers (its own items grid, read through `processPackagePrepare()`) to `polkurier/shipment/pack`,
the shipment screen's action, with the kinds of the packages holding items, or with the
Magento shipment's id on a shipment's page, where the shipment's own items are packed; each
parcel it answers with becomes a package filled in and packed through the popup's own
`newPackage()` and `packItems()`. It runs when the popup opens without packages if
`packing/pack_new_shipments` is on, and from the popup's **Pack automatically** button. It gets the default draft, the
carriers with what each supports, and the choices for every select, from the block's JSON config.
Changing the carrier drops what the new one doesn't support. What's wrong is worked out by the
shipment screen's rules (`workspace/rules.js`: `issues()`, with `featuresOf()` for the carrier,
`shipmentWorth()` and `swapPoint()`), each issue naming the field it's about: a line shows those of
its fields, red, and the label is held back by the blocking ones about the fields the page has
(`fields` in the config), so the two screens check alike and say it in the same words. Polkurier's
refusal shows in the popup like any other label error. The collection date and window
are chosen from those `polkurier/shipment/pickupTimes` returns, as on the shipment screen.

The block also passes `codCollected` and `codRemaining` (see `Model\Shipment\Valuation\OrderAmountCalculator`) and
`items`: the unit value and quantity of each item that ships. The script watches the page's
`shipment[items][…]` fields and keeps the cash on delivery at what the quantities collect
(`rules.codFor()`) and the insurance at what they're worth (`rules.insuranceFor()`), by the
server's rules: the grand total for the whole order. Each stops once it's changed by hand, and
neither is ever turned on by the script.

The pickup point's editor opens the same map as the shipment screen, through
`view/adminhtml/web/js/shipment/points-map.js`, which loads the map widget once, in an iframe away
from the admin's Prototype.js (see [Admin UI](admin-ui.md#the-shipment-screen)), and passes
back the chosen point. The block passes the map token and the order's postcode to start from.

The script also sets the popup's package template to kilograms and centimetres, since Magento
hardcodes pounds and inches there, and adds a **Pack template** list to it, so every package the
popup clones from it has one. Choosing a template (the block passes the pack templates as
`templates`) sets the package type and size and triggers `change` on each field, as if they were
typed; nothing is locked, and the request only carries what the fields hold. A package's weight
follows `parcelWeight` (`rules.parcelWeight()`): the script wraps the popup's
`_recalcContainerWeightAndCustomsValue`, which sets the weight to the sum of the items whenever
they change, and weighs a package with a template again right after. Without a template, the sum
stays, except that a product without a weight leaves the field empty instead of `NaN`.

`Model\Shipment\Screen\ShipmentOptionsProvider` supplies the choices (carriers and their features, sender
addresses, bank accounts). The shipment screen's data provider uses it too.

## The order page

`Plugin\Adminhtml\OrderViewPlugin` (`beforeSetLayout` on the order view) adds **Ship with
Polkurier** when `labels/shipment_screen` is on and the order, placed with a Polkurier method, can
be shipped. Magento's **Ship**
keeps pointing to Magento's page, unless native labels are off, in which case it opens the shipment
screen for orders placed with a Polkurier method.

## Requirements Magento adds

Magento's `Labels::requestToShipment()` refuses to create any label without the store's name and
phone, a complete shipping origin, and a first and last name on the admin user. The module doesn't
use these, since the sender comes from its own address book, but it can't skip the check without a
preference on Magento's class.

Next: [Synchronization](synchronization.md)
