<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Polkurier for Magento 2: user guide

This guide is for store owners and the people who set up and run the store. It walks you through
installing the module, connecting it to your Polkurier account and shipping orders day to day.

If you're a developer and want to know how the module is put together, see the
[developer guide](../developer-guide/README.md).

## Chapters

1. [Introduction](introduction.md): what the module does and what you need
2. [Installation](installation.md): installing, updating, cron and removing the module
3. [Configuration](configuration.md): every setting on the configuration page
4. [Carriers and prices](carriers-and-prices.md): which carriers customers see, and at what price
5. [Addresses, bank accounts and pack templates](master-data.md): the data shared with your
   Polkurier account
6. [Checkout](checkout.md): what your customers see
7. [Shipping an order](shipping-an-order.md): the two ways to place a Polkurier shipment
8. [Managing shipments](managing-shipments.md): labels, protocols, statuses, pickups and
   cancellations
9. [Cash on delivery](cash-on-delivery.md): collecting money and getting it paid out
10. [Permissions](permissions.md): who can do what in the admin
11. [Troubleshooting](troubleshooting.md): common problems and what to do about them
12. [Known limitations](known-limitations.md)

## The short version

1. Install the module and run `setup:upgrade`.
2. In **Stores > Configuration > Sales > Delivery Methods > Polkurier**, enter your customer ID
   and API token, test the connection and set **Enabled** to Yes.
3. In **Polkurier > Settings > Carriers**, fetch your carriers, switch on the ones you
   want to offer and give them a price.
4. Add at least one sender address under **Polkurier > Settings > Addresses**, or let the module
   use your store's shipping origin.
5. Place a test order, then ship it from the order page.

Everything else has sensible defaults you can adjust later.
