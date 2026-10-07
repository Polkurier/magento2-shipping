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
    Model/                 an entity is Model/<Entity>.php, and everything about it in Model/<Entity>/;
                           subsystems without an entity of their own have their own directory
        Address/           repository, search results, admin handler, address book mapper, sync adapter
        Admin/             the admin message an action leaves, and what the forms' buttons post
        BankAccount/       the same, and confirming bank account changes with Polkurier
        Cache/             the module's cache type and the tags of its entries
        Carrier/           the Magento carrier, the carrier catalog and features, logos, rates
            Pricing/       checkout price calculators
        CarrierConfig/     the merchant's carrier settings: repository, loading, saving, refreshing
        Config/            the module's settings; backend, comment and source models of its page
        OrderPoint/        the order's pickup point repository
        PackTemplate/      repository, search results, admin handler, mapper, sync adapter
        Payout/            cash on delivery payouts from the money box
        PickupPoint/       choosing a pickup point at checkout, the map, cash on delivery at a point
        QuotePoint/        the cart's pickup point repository
        ResourceModel/     resource models and collections
        Sdk/               SDK wiring: the account, the Polkurier client, its errors and events
        Shipment/          the shipment service contract, repository, look-ups, the order lock,
                           cancelling, pickups, documents, the admin's attention
            Details/       reading the shipment details, formatting and naming them
            Draft/         building and converting drafts
            Native/        Magento's side: its shipments, comments, labels, the order's shipping
            Packing/       packing items into parcels
            Placement/     placing orders: the order message, validation, recording attempts
            Screen/        what the shipment screen and the label options start from
            Status/        status refresh, reconciliation and Polkurier's webhook
            Valuation/     amounts in złoty, and prices before placing
        ShippingChoice/    the customer's choice of shipping, kept with the order
        Sync/              two-way sync of addresses, bank accounts and pack templates
    Observer/              checkout, order and shipment observers
    Plugin/                plugins on checkout, orders and the admin
    Setup/Patch/Data/      data patches
    Test/Unit/             unit tests
    Ui/                    data providers, columns and buttons for the UI components
    ViewModel/Adminhtml/   view models: the label options, the shipment details page, admin URLs
    etc/                   configuration, with adminhtml and frontend areas
    i18n/                  en_US is built in; pl_PL.csv holds the Polish translation
    view/
        adminhtml/         admin layouts, UI components, templates, JS and CSS
        frontend/          checkout layouts, JS and CSS
```

Paths in the rest of this guide are relative to `src/`.

## Dependencies

The module depends on nothing but Magento itself, the Polkurier SDK and libraries Magento ships
(Monolog, the PSR interfaces, Symfony components): no third-party Magento modules.

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
- Lines, in any file, try to stay within 120 characters, unless wrapping would hurt readability.
  Wrapped code follows PSR-12: a split list puts its first item on the next line, one item per
  line, with a trailing comma. A long condition keeps its first part on the `if (` line, continues on lines indented
  once that start with the operator, and ends with `) {` on its own line:

  ```php
  if ($condition && $condition2
      && $condition3 && $condition4
  ) {
  ```

- Templates use the alternative syntax with no space before the colon: `<?php if (...): ?>`.
- A long string literal in PHP is split into pieces joined with `.`, at word boundaries
  (preferably sentence ends), each continuation line starting with the operator:

  ```php
  __(
      'Polkurier did not accept the API credentials. '
      . 'Check the customer ID and API token in Stores > Configuration > '
      . 'Sales > Delivery Methods > Polkurier. (%1)',
      $e->getErrorMessage()->getResponse(),
  ),
  ```

  Magento's `i18n:collect-phrases` joins such pieces back into one phrase, but only for `__()`
  written without a leading backslash, so translations always use plain `__()`. A text built at
  run time from translated parts is wrapped in `new Phrase(...)`, not `__()`. JavaScript `$t()`,
  Knockout `i18n` bindings and translatable XML text are collected line by line, so their phrases
  stay whole on one line.
- Services use constructor property promotion and `readonly` dependencies.
- A class constant or enum case with a docblock gets a full, multi-line one, and a blank line
  before the docblock and after the constant; undocumented ones may stand together.
- A constant holding a field name or code starts with `FIELD_` (`AddressInterface::FIELD_CITY`,
  `ItemDimensionsProvider::FIELD_LENGTH`), and code names the field through it, not a literal.
  Constants for a field's values keep their own prefix (`ShipmentInterface::STATE_CREATED`).
- In a docblock, the `@param` tags stand together, with a blank line before and after them
  (unless they open or close the docblock).
- Every virtual type's name ends in `Virtual`, e.g. `Polkurier\Shipping\Logger\LoggerVirtual`,
  and every trait's in `Trait`, e.g. `Controller\Adminhtml\ReturnsBackTrait`.
- A class lists its trait uses, then its constants (or enum cases), then its properties, then its
  methods, never mixed; within each, the public ones come first, then the protected, then the
  private.
- `Model/` is organized by entity, as Magento's own modules are: `Model/<Entity>.php` and everything
  about that entity (repository, search results, mapper, handler, sync adapter, services) in
  `Model/<Entity>/`. A subsystem without an entity of its own (`Carrier`, `PickupPoint`, `Sync`,
  `Sdk`) gets a directory of its own. Directories by kind exist only where Magento's conventions
  have them: `ResourceModel/`, `Config/Source|Backend|Comment/`, `Cache/`.
- Objects are created by Magento's object manager: dependencies through the constructor, and
  anything made per use (a message builder, a data object, a result) through a generated factory,
  so preferences and plugins apply. `new` is left for exceptions, PHP's own classes and the SDK's
  small value objects that have no builder.
- A UI component class sits with the form or listing it serves (`Ui\Component\Form\Payout\…`,
  `Ui\Component\Listing\Shipment\…`); only generic ones that several use go without one.
- Class names say what the class is, by its role: `…Repository`, `…Provider`, `…Resolver`,
  `…Builder`, `…Mapper`, `…Finder`, `…Storage`, `…Handler`, `…Manager`, `…Service`. A name that
  only says what the class is about (an adjective, a plural noun, a phrase such as `Payouts` or
  `NotPlaced…`) is not used. Related classes are grouped in a directory by concern rather than
  left side by side.
- Names: an entity is singular snake case wherever the name is free (`bank_account`,
  `polkurier_bank_account_form`); its route is the same without underscores (`bankaccount`),
  because Magento reads `_` in a URL as a sub-directory; ACL and menu ids are the plural for
  seeing and managing a screen and `<entity>_<action>` for a guarded action (`payouts`,
  `payout_request`, `shipment_create`).
- A shared service that keeps anything for the rest of a request (a loaded list, a lock held, a
  message to send) implements `ResetAfterRequestInterface`, so that under an application server,
  where one process serves many requests, the next request starts afresh.
- Admin permissions are checked in the controllers (or in plugins around Magento's own), never in
  models or services.
- Admin controllers that change data implement `HttpPostActionInterface`; the listing buttons
  and row actions that trigger them post with a form key.
- User-facing strings are translatable, and admin messages explain what to do, not just what went
  wrong.

Next: [Talking to Polkurier](talking-to-polkurier.md)
