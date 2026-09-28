<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Introduction

[User guide](README.md) › Introduction

## What the module does

**At checkout**, your customers choose from the carriers you offer through Polkurier: couriers
such as DPD, DHL, UPS or GLS, parcel lockers, pickup shops and so on. They pay the prices you set,
not what Polkurier charges you. For lockers and other pickup points, they pick the point on a map.
Both Magento's regular checkout and the multi-address checkout are supported.

**In the admin**, you ship an order straight from the order page, in one of two ways:

- through **Magento's own shipment page**, where you pack the parcels in Magento's packaging window
  and the Polkurier label is stored with the shipment, like any other carrier's label;
- on the module's **shipment screen**, which puts every option of the shipment on one page and
  works for any order, whatever shipping method the customer chose.

Either way, the shipment starts from what the customer ordered and your defaults, and once
Polkurier accepts it, the Magento order is shipped with the waybill numbers as tracking numbers.

**Afterwards** you can print labels and handover protocols, book a courier pickup for several
shipments at once, follow the delivery status, cancel shipments and request payouts of the cash
on delivery money you collected.

**Behind the scenes**, the module keeps your sender addresses, bank accounts and pack templates the
same in Magento and in your Polkurier account, so you can edit them in either place.

## Your customers never see the Polkurier name

Carriers appear at checkout, in e-mails and on the customer's order page under the labels you give
them, for example "Paczkomat InPost" or "Kurier DPD". The module's own name only shows up in the
admin.

## Requirements

- Magento Open Source or Adobe Commerce 2.4 (tested on 2.4.9), with the standard Luma-based
  checkout
- PHP 8.4 or 8.5
- Magento's cron running on the server, for status updates, synchronization and settling unclear
  shipments
- A Polkurier account with API access. You'll need the customer ID and the API token from the
  Polkurier panel. For a test account (sandbox), contact api@polkurier.pl.

## Several websites

Each Magento website can use its own Polkurier account. Credentials, carriers, prices, addresses,
bank accounts and pack templates all belong to a website. Store views of a website share its
account, but can have their own carrier labels and a few texts of their own.

Next: [Installation](installation.md)
