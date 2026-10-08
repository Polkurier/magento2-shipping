<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Shipping labels in Magento

[Developer guide](README.md) › Shipping labels in Magento

Besides its own shipment screen, the module works with Magento's own label flow, the one online
carriers such as UPS or DHL use: **Create Shipping Label** on the New Shipment page, the packaging
popup, `Magento\Shipping\Model\Shipping\LabelGenerator` and the label stored on `sales_shipment`.
None of Magento's classes is replaced. The flow is on when `labels/native_labels` is on for the
order's store, and only for orders placed with a Polkurier method.

The carrier extends `Magento\Shipping\Model\Carrier\AbstractCarrier`, which declares every hook the
label flow calls. `Magento\Shipping\Model\Carrier\AbstractCarrierOnline` would add nothing the module
needs, and would hide the rates of a cart with a product heavier than a `max_package_weight` the
module doesn't configure.

## The flow

```
New Shipment page, Create Shipping Label
        │
isShippingLabelsAvailable()          yes, whatever the order: Magento doesn't say which one;
        │                            the pages' canCreateShippingLabel() answers for its store
        │
packaging popup                      getContainerTypes(): every parcel type; a pack template
        │                            only fills in a package's fields, which stay editable
        │
requestToShipment()                  places a Polkurier order for the Magento shipment, or
        │                            takes the created one the shipment already has
        ├── refused, or a parcel holds items the shipment doesn't carry ──▶ error, no shipment
        ├── not confirmed ──▶ error, no shipment; reconciliation ships the order later
        │
label, always PDF
        ├── fetched ────▶ every waybill as tracking_number, the label as label_content
        ├── not ready ──▶ tracks added directly, shipment flagged, notice to the admin
        │
Magento saves the shipment and the order, in one transaction
        ├── before ──▶ tracks titled with the carrier's label; a waybill already tracked is skipped
        └── after ───▶ the Polkurier shipment gets its shipment_id; the waybills of a cancelled
                       Polkurier order the shipment had leave its tracking
```

The parcel types set the shipment's type (see [Shipments](shipments.md#the-draft)). The Polkurier
order's draft is the order's default draft with the options posted from the page applied, limited
to [`Model\Shipment\Native\NativeLabelService::OPTION_FIELDS`](../../src/Model/Shipment/Native/NativeLabelService.php);
the recipient always comes from the order. Each package becomes a parcel, converted from pounds and
inches where needed.

A Magento shipment already has a created Polkurier order when it was placed on the shipment screen,
or by a label request whose label wasn't ready; no new order is placed for it.

The label is PDF whatever label format is configured, because Magento merges labels as PDF. A
shipment saved without one is flagged with
[`NativeLabelService::FIELD_NO_LABEL`](../../src/Model/Shipment/Native/NativeLabelService.php).
[Shipments](shipments.md#the-magento-shipment) describes a Magento shipment that had a cancelled
Polkurier order.

If Magento fails after the order was placed, the Polkurier shipment stays `created` without a
Magento shipment, and the status refresh creates the Magento shipment later, without a label.

The `polkurier_api_order_create_after` event fires with `source = "label"`, before Magento saves
its shipment, so `shipment_id` isn't set yet at that point.

## Permissions

A label request from an admin without `Polkurier_Shipping::shipment_create` gets an error. An admin
without `Polkurier_Shipping::pack_templates` loses the packages' pack template names, with a warning.
See [Admin UI](admin-ui.md#permissions) for the other resources.

## The options on the New Shipment page

The label options are the block `polkurier_label_options` in Magento's `extra_shipment_info`
container. It sits inside Magento's shipment form, so its fields are sent with the label request.
It shows only for orders placed with a Polkurier method, when native labels are on and the admin may
place shipments. To change what the page offers, see
[Extending the module](extending.md#the-new-shipment-page).

On the page, **Create Shipping Label** stays ticked and hidden. The options show as summary lines
edited in place, and are checked with the same rules as on the shipment screen. Cash on delivery and
insurance follow the shipped quantities until they're changed by hand. In Magento's packaging popup
the module switches to kilograms and centimetres, adds a **Pack template** list to each package, and
packs automatically when the popup opens without packages, if `packing/pack_new_shipments` is on.

The server doesn't rely on the page keeping the box ticked: for an order placed with a Polkurier
method, a shipment submitted without the label is refused, when the shipment screen or these labels
are on.

## The order page

**Ship with Polkurier** shows when `labels/shipment_screen` is on and the order can be shipped.
Magento's **Ship** opens Magento's page, or the shipment screen when native labels are off.

## Requirements Magento adds

Magento's `Magento\Shipping\Model\Shipping\Labels::requestToShipment()` refuses to create any label
without the store's name and phone, a complete shipping origin, and a first and last name on the
admin user. The module doesn't use these, since the sender comes from its own address book, but it
can't skip the check without a preference on Magento's class.

Next: [Synchronization](synchronization.md)
