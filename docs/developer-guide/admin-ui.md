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
| | **Ship with Polkurier**, after **Ship**, while a Polkurier order can be placed | plugin `polkurier_order_view_buttons` |
| | waybills in the module's comments opening the carrier's tracking page in a new tab | plugin `polkurier_external_comment_links` |
| Shipment page | the label options, and every Polkurier order the shipment has had | blocks `polkurier_label_options`, `polkurier_shipment_orders` |
| | **Add a Polkurier order**, or **Polkurier order** when it has one | plugin `polkurier_shipment_view_buttons` |
| New Shipment page | the label options (see [Shipping labels in Magento](magento-labels.md)) | block `polkurier_label_options` |

On an order shipped with another carrier, the point, the **Polkurier order** column and the buttons
don't show.

Links to other sites open in a new tab: waybills on the details page and in the grids, links in
carrier notices, and waybills in the module's order and shipment comments. Magento drops a comment
link's `target`, so [`Plugin\Adminhtml\ExternalCommentLinksPlugin`](../../src/Plugin/Adminhtml/ExternalCommentLinksPlugin.php)
adds it back to links of the `polkurier-external` class once Magento has escaped the comment.

## Permissions

Shipments use Magento's resources: `Magento_Sales::shipment` for viewing shipments, labels and
protocols and refreshing statuses, and `Magento_Sales::ship` for placing, discarding, adopting and
shipping, collective pickups and labels on Magento's shipment pages. Cancelling has a resource of its own,
`Polkurier_Shipping::shipment_cancel`, under `Magento_Sales::shipment`.

The module's other resources are declared in `etc/acl.xml`, under `Magento_Backend::admin`. Below
the root, `::name` stands for `Polkurier_Shipping::name`:

```
Polkurier_Shipping::root       the Polkurier resources
├── ::sales
│   └── ::payouts              viewing payouts
│       └── ::payout_request   requesting and cancelling payouts
└── ::settings
    ├── ::addresses            the Addresses screen
    ├── ::bank_accounts        the Bank accounts screen
    └── ::pack_templates       the Pack templates screen
```

The Polkurier menu and its Sales group open with `Magento_Sales::sales`, so roles with Magento's
shipment resources reach the shipments list; each item checks its own resource. The Sales group's
Orders item is Magento's orders list, under `Magento_Sales::sales_order`.

The configuration is in Magento's `carriers` section, so it uses `Magento_Shipping::carriers`; so
do the Carriers screen, which sets up the same delivery methods, and the Settings menu group that
holds both.

Permissions are checked in the controllers, not in the services behind them. Buttons and actions a
role can't use aren't shown.

## Controllers

The controllers are internal. Every one that changes data accepts POST only and needs the form key.
After an action, the admin returns to the page it started from.

Next: [Scheduled jobs](cron.md)
