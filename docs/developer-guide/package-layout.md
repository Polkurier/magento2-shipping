<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Package layout

[Developer guide](README.md) › Package layout

The Composer package is `polkurier/magento2-shipping`; the Magento module is `Polkurier_Shipping`.
The module lives in `src/`. The package root holds `composer.json`, which autoloads
`src/registration.php` and maps the `Polkurier\Shipping\` namespace to `src/`, plus the README and
these docs.

```
src/
    Api/                   service contracts and data interfaces
    Block/                 blocks for pages Magento builds from blocks
    Controller/Adminhtml/  admin actions, one directory per screen
    Controller/Map/        the storefront's pickup point map token
    Controller/Webhook/    Polkurier's status webhook
    Cron/                  scheduled jobs
    Enum/                  the module's enums, by the part of the module they belong to: Adminhtml, Cache,
                           CarrierConfig, Config, Sdk, Sync
    Exception/             the module's own exceptions
    Logger/                the module's log channel and the processor that masks secrets
    Model/                 an entity is Model/<Entity>.php, and everything about it in Model/<Entity>/;
                           subsystems without an entity of their own have their own directory
        Address/           sender addresses
        Admin/             the admin message an action leaves, and what the forms' buttons post
        BankAccount/       bank accounts, and confirming their changes with Polkurier
        Cache/             the module's cache type
        Carrier/           the Magento carrier, the account's carriers and what they support, rates
            Pricing/       checkout prices
        CarrierConfig/     the merchant's carrier settings
        Config/            the module's settings and their admin page
        OrderPoint/        the order's pickup point
        PackTemplate/      pack templates
        Payout/            cash on delivery payouts from the money box
        PickupPoint/       choosing a pickup point at checkout, and the map
        QuotePoint/        the cart's pickup point
        ResourceModel/     resource models and collections
        Sdk/               the connection to Polkurier: the account, the client, errors, events
        Shipment/          shipments and what can be done with them
            Details/       the shipment as Polkurier reports it
            Draft/         drafts: what a shipment is to contain
            Native/        Magento's side: its shipments, comments, labels, the order's shipping
            Packing/       packing items into parcels
            Placement/     placing orders
            Screen/        what the shipment screen and the label options start from
            Status/        statuses, reconciliation and Polkurier's webhook
            Valuation/     amounts, and prices before placing
        ShippingChoice/    the customer's choice of shipping, kept with the order
        Sync/              two-way sync of addresses, bank accounts and pack templates
    Observer/              observers, by what they watch: Adminhtml, Checkout, Sales
    Plugin/                plugins, by what they extend: Adminhtml, Checkout, Quote, Sales
    Setup/Patch/Data/      data patches
    Test/Unit/             unit tests
    Ui/                    data providers, columns and buttons for the UI components
    ViewModel/Adminhtml/   view models: the label options, the shipment details page, admin URLs
    etc/                   configuration, with adminhtml and frontend areas
    i18n/                  en_US.csv lists every phrase; pl_PL.csv holds the Polish translation
    view/
        adminhtml/         admin layouts, UI components, templates, JS and CSS
        frontend/          checkout layouts, JS and CSS
```

Paths in the rest of this guide are relative to `src/`.

## Dependencies

Required:

| Package | Why |
|---|---|
| `polkurier/polkurier-sdk` | every call to Polkurier |
| `magento/module-shipping`, `-sales`, `-quote`, `-checkout`, `-payment` | carrier, shipments, checkout integration, cash on delivery |
| `magento/module-sales-sequence` | the Magento shipment number reserved before a shipment is placed |
| `magento/module-catalog`, `-eav` | the product dimensions automatic packing uses |
| `magento/module-backend`, `-config`, `-ui`, `-store`, `-directory`, `-media-storage` | admin screens, settings, grids, carrier logos |
| `monolog/monolog`, `psr/log` | the module's log (see [Logging](logging.md)); works with both Monolog 2 (Magento 2.4.7) and 3 |
| `symfony/serializer`, `symfony/cache`, `psr/http-message` | the SDK's serializer and its cache, and the requests it logs |
| `composer-runtime-api` | the installed SDK's version |

Suggested, not required:

| Package | Without it |
|---|---|
| `magento/module-cron` | nothing runs on schedule, including the check for unclear shipments (see [Installation](installation.md#cron)); synchronize and update shipments by hand in the admin |
| `magento/module-csp` | no Content Security Policy, so the pickup point map needs no whitelisting |
| `polkurier/magento2-multishipping` | no pickup points in the multi-address checkout |

The module's `etc/module.xml` declares the Magento modules it must load after. For the
multi-address checkout bridge, see [Checkout](checkout.md#multi-address-checkout).

Next: [Talking to Polkurier](talking-to-polkurier.md)
