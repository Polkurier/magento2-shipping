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
need to install it separately.

The module adds a **Polkurier** line under **System > Cache Management**: the carriers, pickup
points and map token it reads from Polkurier. It's switched on when the module is installed;
refresh it there, like any other cache, when you want them read again.

### Multi-address checkout

Pickup points in Magento's multi-address checkout (**Check Out with Multiple Addresses**) come
with a separate module, `polkurier/magento2-multishipping`, since that checkout
(`Magento_Multishipping`) is often disabled or removed. Install it only if you use that checkout:

```bash
composer require polkurier/magento2-multishipping
```

```bash
bin/magento module:enable Polkurier_Multishipping
```

```bash
bin/magento setup:upgrade
```

Without it, the regular checkout works as usual, and customers can still check out with multiple
addresses, but with no pickup point to choose for each address.

The module comes with English and Polish translations.

## Cron

Three scheduled jobs keep things up to date. They run through Magento's cron, so make sure
`bin/magento cron:run` is scheduled on the server (Magento's installation guide describes how).
They're in a cron group of their own, `polkurier`, so that a slow answer from Polkurier doesn't
hold up Magento's other jobs. A plain `cron:run` runs every group; if your server runs cron per
group, add `bin/magento cron:run --group=polkurier`.

| Job | How often | What it does |
|---|---|---|
| Synchronization | every hour, at a quarter past | brings addresses, bank accounts and pack templates in line with your Polkurier account |
| Carriers | once a day, at 3:45 | brings the list of carriers in line with what your Polkurier account offers |
| Shipment statuses | every 30 minutes, or as often as set (see [Configuration](configuration.md)) | updates the delivery and cash on delivery status of shipments that aren't finished yet |
| Unclear shipments | every 10 minutes | looks for shipments whose placing wasn't confirmed (see [When the result is unclear](shipping-an-order.md#when-the-result-is-unclear)) |

All but the last can be switched off under **Synchronization** on the configuration page. The
last always runs, because it protects you from shipping the same order twice.

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
