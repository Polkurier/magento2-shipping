<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Installation

[User guide](README.md) › Installation

## Installing

Install with Composer from the Magento root directory:

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

Composer installs the Polkurier SDK (`polkurier/polkurier-sdk`) along with the module; you don't
need to install it separately. The module also needs Magento's multi-address checkout module
(`Magento_Multishipping`), which is part of every standard Magento installation.

The module comes with English and Polish translations.

## Cron

Three scheduled jobs keep things up to date. They run through Magento's cron, so make sure
`bin/magento cron:run` is scheduled on the server (Magento's installation guide describes how).

| Job | How often | What it does |
|---|---|---|
| Synchronization | every hour, at a quarter past | brings addresses, bank accounts and pack templates in line with your Polkurier account |
| Shipment statuses | every 30 minutes | updates the delivery and cash on delivery status of shipments that aren't finished yet |
| Unclear shipments | every 10 minutes | looks for shipments whose placing wasn't confirmed (see [When the result is unclear](shipping-an-order.md#when-the-result-is-unclear)) |

The first two can be switched off under **Synchronization** on the configuration page. The third
always runs, because it protects you from shipping the same order twice.

## Updating

```bash
composer update polkurier/magento2-shipping
```

```bash
bin/magento setup:upgrade
```

In production, follow with the same compile, deploy and cache steps as above.

## Removing the module

```bash
bin/magento module:disable Polkurier_Shipping
```

```bash
composer remove polkurier/magento2-shipping
```

The module's database tables stay in place, so no shipment history is lost if you change your
mind. If you're sure you don't need them, you can drop the `polkurier_*` tables by hand.

Orders placed with a Polkurier shipping method keep their shipping description and tracking
numbers after the module is removed.

Next: [Configuration](configuration.md)
