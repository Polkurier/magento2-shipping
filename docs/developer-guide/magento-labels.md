<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Shipping labels in Magento

[Developer guide](README.md) › Shipping labels in Magento

Besides its own shipment screen, the module works with Magento's own label flow, the one online
carriers such as UPS or DHL use: **Create Shipping Label** on the New Shipment page, the packaging
popup, `Magento\Shipping\Model\Shipping\LabelGenerator` and the label stored on `sales_shipment`.
None of Magento's classes is replaced. The flow is there for every order placed with a Polkurier
method.

The carrier extends `Magento\Shipping\Model\Carrier\AbstractCarrier`, which declares every hook the
label flow calls. `Magento\Shipping\Model\Carrier\AbstractCarrierOnline` would add nothing the module
needs, and would hide the rates of a cart with a product heavier than a `max_package_weight` the
module doesn't configure.

## The flow

```
New Shipment page, Create Shipping Label
        │
isShippingLabelsAvailable()          yes
        │
packaging popup                      getContainerTypes(): every parcel type; a pack template
        │                            only fills in a package's fields, which stay editable
        │
requestToShipment()                  places a Polkurier order for the Magento shipment, or
        │                            takes the created one the shipment already has
        ├── refused, or a parcel holds items the shipment doesn't carry ──▶ error, no shipment
        ├── not confirmed ──▶ error, no shipment saved; reconciliation ships the order later
        │
label, always PDF
        ├── fetched ────▶ every waybill as tracking_number, the label as label_content
        ├── not ready ──▶ tracks added directly, shipment flagged, notice to the admin
        │
Magento saves the shipment and the order, in one transaction
        ├── before ──▶ tracks titled with the carrier's label; a waybill already tracked is skipped
        └── after ───▶ the Polkurier shipment gets its shipment_id; the waybills of a cancelled
        │              Polkurier order the shipment had leave its tracking
        │
Magento's shipment e-mail, if Email Copy of Shipment is ticked (New Shipment page only)
```

The parcel types set the shipment's type (see [Shipments](shipments.md#the-draft)). The Polkurier
order's draft is the order's default draft with the options posted from the page applied, limited to
[`Model\Shipment\Native\NativeLabelService::OPTION_FIELDS`](../../src/Model/Shipment/Native/NativeLabelService.php);
the recipient always comes from the order. Each package becomes a parcel, converted from pounds and
inches where needed.

A Magento shipment already has a created Polkurier order when it was placed on the shipment screen,
or by a label request whose label wasn't ready; no new order is placed for it.

A label for an existing Magento shipment records that shipment as the attempt's
`target_shipment_id`. If the order isn't confirmed and reconciliation finds it placed, it goes on
that shipment: its waybills become tracks and its `shipment_id` is set. For a Magento shipment being
created, reconciliation creates it with the quantities stored with the attempt.

The label is PDF, because Magento merges labels as PDF. A shipment saved without one is flagged
with
[`NativeLabelService::FIELD_NO_LABEL`](../../src/Model/Shipment/Native/NativeLabelService.php).
[Shipments](shipments.md#the-magento-shipment) describes a Magento shipment that had a cancelled
Polkurier order.

If Magento fails after the order was placed, the Polkurier shipment stays `created` without a
Magento shipment, and the status refresh creates the Magento shipment later, without a label.

**The customer's e-mail.** Magento sends it after saving the shipment
(`Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save`), which follows the label, so only for
a placed Polkurier order: a refused or unconfirmed one throws before anything is saved. The draft
records Magento's **Email Copy of Shipment** in `notify_customer`, for a Magento shipment the module
creates later, if the order was unconfirmed and is then found placed; that e-mail waits for the
waybills, as described in [Shipments](shipments.md#the-magento-shipment). A label for an existing
Magento shipment (`Magento\Shipping\Controller\Adminhtml\Order\Shipment\CreateLabel`) e-mails
nobody; Magento's **Send Tracking Information** on the shipment's page does.

The `polkurier_api_order_create_after` event fires with `source = "label"`, before Magento saves
its shipment, so `shipment_id` isn't set yet at that point.

## Permissions

A label request from an admin without `Polkurier_Shipping::shipment_create` gets an error. An admin
without `Polkurier_Shipping::pack_templates` loses the packages' pack template names, with a warning.
See [Admin UI](admin-ui.md#permissions) for the other resources.

## The options on the New Shipment page

The label options are the block `polkurier_label_options` in Magento's `extra_shipment_info`
container. It sits inside Magento's shipment form, so its fields are sent with the label request. It
shows for orders placed with a Polkurier method, when the admin may place shipments. To change what
the page offers, see [Extending the module](extending.md#the-new-shipment-page).

**Create Shipping Label** is Magento's own checkbox: ticked, the label places the Polkurier order;
unticked, Magento saves the shipment without one, for its Polkurier order to be added later from the
shipment's page (**Create Shipping Label** or **Add a Polkurier order**). An unticked save is refused
([`Plugin\Adminhtml\ShipmentSavePlugin`](../../src/Plugin/Adminhtml/ShipmentSavePlugin.php)) while
the order has a Polkurier order that is `pending` or `unknown`, or `created` without a Magento
shipment, and has no `target_shipment_id`: its Magento shipment is made later from the items the
order has left, which the new shipment would take.

The options show as summary lines edited in place, and are checked with the same rules as on the
shipment screen. Cash on delivery and insurance follow the shipped quantities until they're changed
by hand. In Magento's packaging popup the module switches to kilograms and centimetres, adds a
**Pack template** list to each package, and packs automatically when the popup opens without
packages, if `packing/pack_new_shipments` is on.

## The order page

**Ship with Polkurier** shows while the order can be shipped, next to Magento's **Ship**, which opens
Magento's page.

## Requirements Magento adds

Magento's `Magento\Shipping\Model\Shipping\Labels::requestToShipment()` refuses to create any label
without the store's name and phone, a complete shipping origin, and a first and last name on the
admin user. The module doesn't use these, since the sender comes from its own address book, but it
can't skip the check without a preference on Magento's class.

Next: [Synchronization](synchronization.md)
