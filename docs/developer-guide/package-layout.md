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
    Api/                   service contracts, data interfaces, event names
    Block/                 blocks for pages Magento still builds from blocks
    Controller/Adminhtml/  admin actions, one directory per screen
    Cron/                  scheduled jobs
    Logger/                the module's log channel and the processor that masks secrets
    Model/
        Admin/             save and delete handlers behind the settings screens
        BankAccount/       confirming bank account changes with Polkurier
        Carrier/           the Magento carrier, the carrier catalog, carrier features and settings
        Checkout/          pickup point selection and the map configuration
        Mapper/            Magento data <-> SDK messages
        MoneyBox/          cash on delivery payouts
        Pricing/           checkout price calculators
        Sdk/               SDK wiring: per-store configuration and the client pool
        Shipment/          drafts, placing, reconciling, labels and following shipments
        Sync/              two-way sync of addresses, bank accounts and pack templates
    Observer/              checkout, order and shipment observers
    Plugin/                plugins on checkout, multi-address checkout, orders and the admin
    Setup/Patch/Data/      data patches
    Test/Unit/             unit tests
    Ui/                    data providers, columns and buttons for the UI components
    etc/                   configuration, with adminhtml, frontend and webapi_rest areas
    i18n/                  en_US is built in; pl_PL.csv holds the Polish translation
    view/
        adminhtml/         admin layouts, UI components, templates, JS and CSS
        frontend/          checkout layouts, JS and CSS
```

Paths in the rest of this guide are relative to `src/`.

## Dependencies

| Package | Why |
|---|---|
| `polkurier/polkurier-sdk` | every call to Polkurier |
| `magento/module-shipping`, `-sales`, `-quote`, `-checkout`, `-payment` | carrier, shipments, checkout integration |
| `magento/module-multishipping` | pickup points in the multi-address checkout |
| `magento/module-offline-payments` | Magento's cash on delivery method, the default for cash on delivery orders |
| `magento/module-backend`, `-config`, `-ui`, `-cron` | admin screens and scheduled jobs |

The module's `etc/module.xml` declares the Magento modules it must load after.

## Conventions

- Every PHP file starts with `declare(strict_types=1)` and the copyright header.
- Services use constructor property promotion and `readonly` dependencies.
- Admin controllers that change data implement `HttpPostActionInterface`; the listing buttons
  and row actions that trigger them post with a form key.
- User-facing strings are translatable, and admin messages explain what to do, not just what went
  wrong.

Next: [Talking to Polkurier](talking-to-polkurier.md)
