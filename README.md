<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Polkurier for Magento 2

Ship your Magento orders with [Polkurier](https://www.polkurier.pl).

- Offer Polkurier's carriers at checkout, at your own prices and under your own names.
- Let customers pick a parcel locker or pickup point on a map.
- Ship from Magento's own shipment page, with the Polkurier label stored like any carrier's, or
  from a shipment screen that has every option on one page.
- Print labels and protocols, book courier pickups and follow deliveries.
- Keep track of cash on delivery and request payouts.

```bash
composer require polkurier/magento2-shipping
bin/magento module:enable Polkurier_Shipping
bin/magento setup:upgrade
```

Requires Magento 2.4 and PHP 8.4 or 8.5.

## Documentation

- [User guide](docs/user-guide/README.md): installation, configuration and day-to-day use
- [Developer guide](docs/developer-guide/README.md): how the module works and how to extend it
