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
- Pack orders into your pack templates automatically, by product size and weight, and ship an
  order in parts.
- Print labels and protocols, book courier pickups and follow deliveries.
- Keep track of cash on delivery and request payouts.
- Keep sender addresses, bank accounts and pack templates in step with your Polkurier account.
- Works with the multi-address checkout through `polkurier/magento2-multishipping`; REST API and
  events for integrations.

```bash
composer require polkurier/magento2-shipping
bin/magento module:enable Polkurier_Shipping
bin/magento setup:upgrade
```

Requires Magento Open Source or Adobe Commerce 2.4.7 or later, and PHP 8.3, 8.4 or 8.5.

## Documentation

- [User guide](docs/user-guide/README.md): installation, configuration and day-to-day use
- [Developer guide](docs/developer-guide/README.md): how the module works and how to extend it
