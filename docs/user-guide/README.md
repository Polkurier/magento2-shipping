<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Polkurier for Magento 2: user guide

See also the [developer guide](../developer-guide/README.md).

## Chapters

1. [Introduction](introduction.md): what the module does and what you need
2. [Configuration](configuration.md): every setting on the configuration page
3. [Carriers and prices](carriers-and-prices.md): which carriers customers see, and at what price
4. [Addresses, bank accounts and pack templates](synced-data.md): the data shared with your
   Polkurier account
5. [Checkout](checkout.md): what your customers see
6. [Shipping an order](shipping-an-order.md): the two ways to place a Polkurier shipment
7. [Managing shipments](managing-shipments.md): labels, protocols, statuses, pickups and
   cancellations
8. [Cash on delivery](cash-on-delivery.md): collecting money and getting it paid out
9. [Permissions](permissions.md): who can do what in the admin
10. [Troubleshooting](troubleshooting.md): common problems and what to do about them
11. [Known limitations](known-limitations.md)

## Getting started

1. Go to **Stores > Configuration > Sales > Delivery Methods > Polkurier**. Enter your customer ID
   and API token, click **Test connection**, set **Enabled** to Yes and click **Save Config**.
   **Sandbox** is on at first, for a test account; switch it off for your live account.
2. Go to **Polkurier > Settings > Carriers**. Click **Synchronize with Polkurier**, switch on the
   carriers you want to offer and give them a price.
3. Add at least one sender address under **Polkurier > Settings > Addresses**, or let the module
   use your store's shipping origin.
4. Place a test order, then ship it from the order page.
