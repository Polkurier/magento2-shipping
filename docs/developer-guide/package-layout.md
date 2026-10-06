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
    Block/                 blocks for pages Magento still builds from blocks
    Controller/Adminhtml/  admin actions, one directory per screen
    Controller/Map/        the storefront's pickup point map token
    Controller/Webhook/    Polkurier's status webhook
    Cron/                  scheduled jobs
    Enum/                  the module's enums, the events and synchronized kinds of entries among them
    Exception/             the module's own exceptions
    Logger/                the module's log channel and the processor that masks secrets
    Model/
        Admin/             save and delete handlers behind the settings screens
        BankAccount/       confirming bank account changes with Polkurier
        Cache/             the module's cache type and the tags of its entries
        Carrier/           the Magento carrier, the carrier catalog, carrier features and settings
        Checkout/          pickup point selection and the map configuration
        Mapper/            Magento data <-> SDK messages
        MoneyBox/          cash on delivery payouts
        Pricing/           checkout price calculators
        Sdk/               SDK wiring: per-store configuration and the client pool
        Shipment/          drafts, placing, reconciling, labels and following shipments
        Sync/              two-way sync of addresses, bank accounts and pack templates
        Webhook/           what Polkurier's status webhook says
    Observer/              checkout, order and shipment observers
    Plugin/                plugins on checkout, orders and the admin
    Setup/Patch/Data/      data patches
    Test/Unit/             unit tests
    Ui/                    data providers, columns and buttons for the UI components
    etc/                   configuration, with adminhtml and frontend areas
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
| `magento/module-shipping`, `-sales`, `-quote`, `-checkout`, `-payment` | carrier, shipments, checkout integration, cash on delivery |
| `magento/module-catalog`, `-eav` | the product dimensions automatic packing uses |
| `magento/module-backend`, `-config`, `-ui`, `-store`, `-directory`, `-customer`, `-media-storage` | admin screens, settings, grids, carrier logos |
| `monolog/monolog`, `psr/log` | the module's log, `var/log/polkurier/YYYY-MM-access.log` and `-error.log`; Monolog 2 (Magento 2.4.7) and 3 alike |
| `symfony/serializer`, `symfony/cache`, `psr/http-message` | the SDK's serializer and its cache, and the requests it logs |
| `composer-runtime-api` | the SDK's version, which keys the cached carrier list |

Suggested, not required:

| Package | Without it |
|---|---|
| `magento/module-cron` | nothing runs on schedule, the look for unclear shipments included (see the user guide's Installation, Cron); synchronize and update shipments from the admin by hand |
| `magento/module-csp` | nothing to whitelist the pickup point map for |
| `polkurier/magento2-multishipping` | no pickup points in the multi-address checkout |

The module's `etc/module.xml` declares the Magento modules it must load after.

The multi-address checkout (`Magento_Multishipping`) is often disabled or removed, so it's left to
a bridge module, `polkurier/magento2-multishipping` (`Polkurier_Multishipping`), which requires
both. This module only suggests it, and nothing here refers to `Magento\Multishipping`; see
[Checkout](checkout.md#multi-address-checkout).

## Conventions

- Every PHP file starts with `declare(strict_types=1)` and the copyright header.
- Services use constructor property promotion and `readonly` dependencies.
- A class constant or enum case with a docblock gets a full, multi-line one, and a blank line
  before the docblock and after the constant; undocumented ones may stand together.
- In a docblock, the `@param` tags stand together, with a blank line before and after them
  (unless they open or close the docblock).
- Admin controllers that change data implement `HttpPostActionInterface`; the listing buttons
  and row actions that trigger them post with a form key.
- User-facing strings are translatable, and admin messages explain what to do, not just what went
  wrong.

Next: [Talking to Polkurier](talking-to-polkurier.md)
