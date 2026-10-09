<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Admin UI

[Developer guide](README.md) › Admin UI

The module's screens are UI components; what it adds to Magento's own pages is layout blocks and
plugins. A same-named UI component file adds columns, fields and buttons, a layout update moves or
removes a block, and a disabled plugin removes a button.

## Screens

| Screen | UI component | Route |
|---|---|---|
| Shipments | `polkurier_shipment_listing` | `polkurier/shipment/index` |
| Shipment details | `polkurier_shipment_view` | `polkurier/shipment/view` |
| Shipment screen (placing a shipment) | `polkurier_shipment_form` | `polkurier/shipment/create` |
| Collective pickup | `polkurier_shipment_pickup_form` | `polkurier/shipment/pickupForm` |
| Cash on delivery payouts | `polkurier_payout_listing`, `polkurier_payout_form` | `polkurier/payout/*` |
| Carriers | `polkurier_carrier_listing`, `polkurier_carrier_form` | `polkurier/carrier/*` |
| Addresses | `polkurier_address_listing`, `polkurier_address_form` | `polkurier/address/*` |
| Bank accounts | `polkurier_bank_account_listing`, `polkurier_bank_account_form` | `polkurier/bankaccount/*` |
| Pack templates | `polkurier_pack_template_listing`, `polkurier_pack_template_form` | `polkurier/packtemplate/*` |

Each listing's `<component>_data_source` is mapped to its collection in `etc/di.xml`, in the
`collections` argument of Magento's grid collection factory; map it to your own collection there to
change what a list shows.

The shipment screen sends only the shipment's draft fields when it places a shipment, so a field you
add to `polkurier_shipment_form` isn't saved. To change what a shipment contains, use the plugins in
[Extending the module](extending.md#changing-the-default-shipment). The shipment screen and the
label options on Magento's New Shipment page start from the same data, so a change there shows on
both.

## Magento's pages

| Page | What the module adds | Where |
|---|---|---|
| Orders grid | a carrier filter; the Polkurier menu's *Orders* opens the grid filtered to the module's orders | `sales_order_grid` |
| Order page | the customer's pickup point and the carrier the parcels went with, under the shipping method | blocks `polkurier_order_point`, `polkurier_shipped_with` |
| | a **Polkurier** tab with the order's shipments in every state, including those not placed | `polkurier_order_view_shipment_listing` |
| | a **Polkurier order** column in the Shipments tab | `sales_order_view_shipment_grid` |
| | **Ship with Polkurier**, after **Ship** | plugin `polkurier_order_view_buttons` |
| Shipment page | the label options, and every Polkurier order the shipment has had | blocks `polkurier_label_options`, `polkurier_shipment_orders` |
| | **Add a Polkurier order**, or **Polkurier order** when it has one | plugin `polkurier_shipment_view_buttons` |
| New Shipment page | the label options (see [Shipping labels in Magento](magento-labels.md)) | block `polkurier_label_options` |

On an order shipped with another carrier, the point, the **Polkurier order** column and the buttons
don't show.

## Permissions

Resources are declared in `etc/acl.xml`, under `Magento_Backend::admin`. Below the root, `::name`
stands for `Polkurier_Shipping::name`:

```
Polkurier_Shipping::root       the Polkurier menu
├── ::sales                    the Sales menu; its Orders item is Magento's orders list,
│   │                          under Magento_Sales::sales_order
│   ├── ::shipments            viewing shipments, labels and protocols; refreshing statuses
│   │   ├── ::shipment_create  placing, reconciling, adopting and shipping; collective pickups;
│   │   │                      labels on Magento's shipment pages
│   │   └── ::shipment_cancel  cancelling shipments
│   └── ::payouts              viewing payouts
│       └── ::payout_request   requesting and cancelling payouts
└── ::settings                 the Settings menu
    ├── ::carriers             the Carriers screen
    ├── ::addresses            the Addresses screen
    ├── ::bank_accounts        the Bank accounts screen
    └── ::pack_templates       the Pack templates screen
```

The configuration is in Magento's `carriers` section, so it uses `Magento_Shipping::carriers`.

Permissions are checked in the controllers, not in the services behind them. Buttons and actions a
role can't use aren't shown.

## Controllers

The controllers are internal. Every one that changes data accepts POST only and needs the form key.
After an action, the admin returns to the page it started from.

Next: [Scheduled jobs](cron.md)
