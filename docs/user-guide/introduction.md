<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Introduction

[User guide](README.md) › Introduction

## What the module does

**At checkout**, customers choose from the carriers you offer: couriers such as DPD, DHL, UPS or
GLS, parcel lockers, pickup shops and so on. They pay the prices you set, not what Polkurier
charges you. For a locker or other pickup point, they pick the point on a map.

**In the admin**, you ship an order from the order page, in one of two ways:

- on **Magento's own shipment page**, where you pack the parcels in Magento's packaging window.
  The Polkurier label is stored with the shipment, like any other carrier's label.
- on the module's **shipment screen**, which has every option of the shipment on one page.

Either way, the shipment is filled in from the order and your defaults. Once Polkurier accepts it,
the Magento order is shipped, with the waybill numbers as tracking numbers.

Only orders placed with a Polkurier delivery method are shipped this way. Orders placed with
another carrier's method get no Polkurier buttons or sections, so they stay with that carrier.

**Afterwards**, you can print labels and handover protocols, book a courier pickup for several
shipments at once, follow the delivery status, cancel shipments and request payouts of cash on
delivery money.

**Behind the scenes**, the module keeps your sender addresses, bank accounts and pack templates the
same in Magento and in your Polkurier account, so you can edit them in either place.

## What you need

- **A Polkurier account with API access.** You'll need the customer ID and the API token from the
  Polkurier panel. For a test account (sandbox), write to api@polkurier.pl.
- **Magento 2.4.7 or later, with the standard checkout.** Other checkouts aren't supported (see
  [Known limitations](known-limitations.md)).
- **Magento's scheduled jobs (cron) running on the server.** They update shipment statuses, keep
  your data in step with Polkurier and check shipments whose result was unclear.

The module adds a **Polkurier** menu to the admin. Its settings are under **Stores > Configuration
> Sales > Delivery Methods > Polkurier**. The admin and checkout are available in English and Polish.

### Multi-address checkout

Pickup points in **Check Out with Multiple Addresses** come with a separate add-on,
`polkurier/magento2-multishipping` ([installation](../developer-guide/installation.md#multi-address-checkout)).
Without it, that checkout still works, but customers can't choose a pickup point in it.

## Several websites

One Polkurier account serves the whole Magento installation. Separate accounts for different
websites aren't supported.

All websites share the credentials, carriers, prices, addresses, bank accounts and pack templates.
Most other settings, such as the shipment defaults, can differ between websites. Each store view
can have its own carrier labels and a few texts of its own.

Next: [Configuration](configuration.md)
