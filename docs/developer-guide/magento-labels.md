<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Shipping labels in Magento

[Developer guide](README.md) › Shipping labels in Magento

Besides its own shipment screen, the module plugs into Magento's own label flow, the one online
carriers such as UPS or DHL use: **Create Shipping Label** on the New Shipment page, the packaging
popup, `Magento\Shipping\Model\Shipping\LabelGenerator` and the label stored on `sales_shipment`.
The aim is to use Magento's mechanism as it is, with as few overrides as possible: there are no
preferences or plugins on Magento's classes for this, only the carrier's own methods, one block and
two observers.

## Why `AbstractCarrier`

The carrier stays on `AbstractCarrier`, which already declares every hook the label flow calls.
`AbstractCarrierOnline` adds nothing the module needs, and its `processAdditionalValidation()`
would hide the rates of any cart with a product heavier than a `max_package_weight` the module
doesn't configure.

## The flow

1. **Is it available?** Magento asks the order's carrier `isShippingLabelsAvailable()` before
   showing the checkbox. The carrier answers from `shipments/native_labels`, via
   `Model\Shipment\NativeLabel::isAvailable()`. Only orders placed with a Polkurier method get this
   far, because Magento always asks the order's own carrier.
2. **Packaging popup.** Magento asks the carrier for container types.
   `NativeLabel::getContainerTypes()` returns the parcel types (`ST`, `NST`, `DLU`, `PPAL`, `PAL`),
   all customizable. Pack templates are not container types: they fill in a package from a list of
   their own (see below), so the package's fields stay editable. `isGirthAllowed()` answers no,
   and there are no delivery confirmation or content types.
3. **Label request.** On save, Magento calls the carrier's `requestToShipment()` with the shipment
   and the packages, and the carrier hands it to `NativeLabel::request()`:
   - it checks the `Polkurier_Shipping::shipment_create` permission;
   - it builds the default draft for the order, and applies the options posted from the New
     Shipment page (the `polkurier[...]` fields, limited to `NativeLabel::OPTION_FIELDS`; the
     recipient always comes from the order);
   - it turns the packages into parcels, one per package, converting pounds and inches;
   - it calls `ShipmentManagement::placeForMagentoShipment()`, which places the order without
     shipping the Magento order, and throws when the outcome is unclear, so Magento doesn't save a
     shipment without tracking. Reconciliation ships the order later;
   - it marks the Magento shipment with the Polkurier shipment's id (`NativeLabel::LINK`).
4. **The answer** is the single entry Magento expects: every waybill as `tracking_number` (Magento
   accepts a list there) and the label as `label_content`. The label is always fetched as PDF,
   whatever label format is configured, because Magento merges labels as PDF documents.
5. **No label yet.** If the label can't be fetched, the answer is empty, the tracks are added to
   the shipment directly, the shipment is flagged (`NativeLabel::NO_LABEL`) and the admin gets a
   notice.
6. **Saving.** Magento saves the shipment and the order in one transaction. Two observers in the
   admin area finish the job:
   - `Observer\PrepareLabelledShipment` (`sales_order_shipment_save_before`) retitles the tracks
     Magento added with the carrier's admin title (`carriers/polkurier/title`, which customers must
     not see) to the shipping method's name, skips waybills the shipment already tracks, and leaves
     out the label when the shipment is flagged, so no empty document is stored;
   - `Observer\LinkLabelledShipment` (`sales_order_shipment_save_after`) sets `shipment_id` on the
     Polkurier shipment, in the same transaction.

A Magento shipment that already has a Polkurier order (one created on the shipment screen, or a
label request whose label wasn't ready) gets that order's label on **Create Shipping Label**, and no
new order is placed.

If Magento fails after the order was placed, the Polkurier shipment stays `created` without a
Magento shipment, and the status refresh creates one later, without a label, like any other
shipment whose Magento shipment is missing.

The `polkurier_api_order_create_after` event fires with `source = "label"`, before Magento saves
its shipment, so `shipment_id` isn't set yet at that point.

## The options on the New Shipment page

`Block\Adminhtml\Shipment\LabelOptions` (template `shipment/label-options.phtml`) is added to the
`extra_shipment_info` container by `view/adminhtml/layout/adminhtml_order_shipment_new.xml`. That
container is inside Magento's shipment form, so the block's hidden fields are sent with the label
request: the packaging popup serializes the whole form when it opens. The block only renders for
orders placed with a Polkurier method, with native labels on, for admins allowed to place
shipments.

`view/adminhtml/web/js/shipment/label-options.js` shows one summary line per group of options, each
edited in its own modal, and keeps the values in the hidden fields. It gets the default draft, the
carriers with what each supports, and the choices for every select, from the block's JSON config.
Changing the carrier drops what the new one doesn't support. Lines turn red for a missing required
point or collection date; nothing else is checked on the page, and Polkurier's refusal shows in the
popup like any other label error.

The pickup point modal opens the same map as the shipment screen, through
`view/adminhtml/web/js/shipment/points-map.js`, which loads the map widget once, in an iframe away
from the admin's Prototype.js (see [Admin UI](admin-ui.md#the-shipment-screen)), and passes
back the chosen point. The block passes the map token and the order's postcode and town to start from.

The script also sets the popup's package template to kilograms and centimetres, since Magento
hardcodes pounds and inches there, and adds a **Pack template…** list to it, so every package the
popup clones from it has one. Choosing a template (the block passes the website's templates as
`templates`) sets the package type, size and weight and triggers `change` on each field, as if
they were typed; nothing is locked, and the request only carries what the fields hold.

`Model\Shipment\ShipmentOptions` supplies the choices (carriers and their features, sender
addresses, bank accounts). The shipment screen's data provider uses it too.

## The order page

`Plugin\Adminhtml\OrderViewPlugin` (`beforeSetLayout` on the order view) adds **Ship with
Polkurier** when `shipments/shipment_screen` is on and the order can be shipped. Magento's **Ship**
keeps pointing to Magento's page, unless native labels are off, in which case it opens the shipment
screen for orders placed with a Polkurier method.

## Requirements Magento adds

Magento's `Labels::requestToShipment()` refuses to create any label without the store's name and
phone, a complete shipping origin, and a first and last name on the admin user. The module doesn't
use these, since the sender comes from its own address book, but it can't skip the check without a
preference on Magento's class.

Next: [Synchronization](synchronization.md)
