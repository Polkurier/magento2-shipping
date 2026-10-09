<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Installation

[Developer guide](README.md) › Installation

## Requirements

- Magento Open Source or Adobe Commerce 2.4.7 or later (tested on 2.4.9), with the standard
  Luma-based checkout
- PHP 8.3, 8.4 or 8.5, as far as your Magento version supports it: 2.4.7 runs on PHP 8.3, 2.4.8
  on 8.3 and 8.4, 2.4.9 on 8.3 to 8.5
- Magento's cron (see [Cron](#cron))

## Installing

From the Magento root directory:

```bash
composer require polkurier/magento2-shipping
```

```bash
bin/magento module:enable Polkurier_Shipping
```

```bash
bin/magento setup:upgrade
```

In production mode, also compile the code, deploy the static files and clear the cache:

```bash
bin/magento setup:di:compile
```

```bash
bin/magento setup:static-content:deploy pl_PL en_US
```

```bash
bin/magento cache:flush
```

Composer installs the Polkurier SDK (`polkurier/polkurier-sdk`) along with the module. The module
comes with English and Polish translations.

The module adds its own cache type, `polkurier`, shown as **Polkurier** under **System > Cache
Management**. It holds the carriers, pickup points and map token read from Polkurier, and is
switched on at installation. Refresh it there, or with `bin/magento cache:clean polkurier`, to have
them read again.

### Multi-address checkout

Pickup points in Magento's multi-address checkout need the add-on module
`polkurier/magento2-multishipping`. Install it only if the shop uses that checkout:

```bash
composer require polkurier/magento2-multishipping
```

```bash
bin/magento module:enable Polkurier_Multishipping
```

```bash
bin/magento setup:upgrade
```

Without it, the multi-address checkout still works, with no pickup point to choose per address.

## Cron

The module's [scheduled jobs](cron.md) run through Magento's cron, so `bin/magento cron:run` has to
be scheduled on the server, as Magento's installation guide describes. The jobs are in a cron group
of their own, `polkurier`, which runs in a separate process. A plain `cron:run` runs every group; a
server that runs cron per group also needs:

```bash
bin/magento cron:run --group=polkurier
```

Without cron, shipment statuses, synchronized data and unclear shipments aren't updated on their
own. The merchant can still synchronize and update shipments by hand in the admin.

## Updating

```bash
composer update polkurier/magento2-shipping
```

```bash
bin/magento setup:upgrade
```

In production, follow with the same compile, deploy and cache steps as when installing.

## Removing

```bash
bin/magento module:disable Polkurier_Shipping
```

```bash
composer remove polkurier/magento2-shipping
```

The module's database tables (`polkurier_*`, see [Database](database.md)) stay in place, so no
shipment history is lost. Drop them by hand if they're no longer needed. Orders placed with a
Polkurier shipping method keep their shipping description and tracking numbers.

## A copy of the shop

A copy that shares the shop's encryption key and Polkurier account, such as a staging copy, needs
its own installation ID, so that its orders aren't mistaken for the shop's (see
[Reconciliation](shipments.md#reconciliation)). A copied database brings the setting along, so set
it in `app/etc/env.php` on the copy:

```bash
bin/magento config:set --lock-env carriers/polkurier/general/application_id shop-staging
```

The ID takes up to 50 letters, digits, dots, dashes and underscores. Orders already placed keep the
ID they were placed with.

Next: [Package layout](package-layout.md)
