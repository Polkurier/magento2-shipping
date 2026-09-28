<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Managing shipments

[User guide](README.md) › Managing shipments

## Where to find them

- **On the order page**, the Information tab lists the order's **Polkurier shipments**.
- **Polkurier > Sales > Shipments** lists the shipments of all orders, with filters, search and
  the collective pickup.

Two more lists under **Polkurier > Sales** show Magento's own records, narrowed to what you ship
with Polkurier, so you don't have to filter Magento's lists:

- **Orders**: Magento's orders list, with only the orders placed with a Polkurier method or
  shipped through Polkurier. Its **Actions** menu is Magento's: cancel, hold and unhold, and print
  invoices, packing slips, credit memos, all of these at once, or shipping labels.
- **Store shipments**: Magento's shipments of those orders, with Magento's **PDF Shipments** and
  **Print Shipping Labels** actions.

Every attempt to place a shipment is listed, including refused ones, so you can see what happened
and why.

## What the list shows

| Column | |
|---|---|
| Order | the Magento order (Shipments page only) |
| Created | when the shipment was placed |
| Carrier | the Polkurier carrier code |
| State | see below |
| Polkurier order | the order number in your Polkurier account |
| Waybills | the carrier's tracking numbers, linked to the carrier's tracking page |
| Delivery status, Status date | the latest status reported by the carrier |
| Cash on delivery, COD status | the amount to collect and where the money is (see [Cash on delivery](cash-on-delivery.md#following-the-money)) |
| Cost (gross) | what Polkurier charged you |
| Collective pickup, Pickup booked | whether the shipment waits for a collective pickup, and whether it's booked (Shipments page only) |
| Last error | why the last action on the shipment failed, if it did |

The **State** tells you where the shipment stands with Polkurier:

| State | Meaning |
|---|---|
| Sending | being placed right now |
| Placed | Polkurier accepted it |
| Outcome unknown | Polkurier didn't confirm it; it's being checked (see [When the result is unclear](shipping-an-order.md#when-the-result-is-unclear)) |
| Not placed | Polkurier refused it, or it turned out not to exist; the order can be shipped again |
| Cancelled | cancelled in Polkurier |

## Actions on a shipment

What each row offers depends on its state.

For placed shipments:

- **Print label** downloads the shipping label, in the **Label format** you configured.
- **Print protocol** downloads the handover protocol, which the courier signs when collecting.
- **Update status** asks Polkurier for the current delivery and cash on delivery status.
- **Create Magento shipment** appears when the Magento order hasn't been shipped yet (see below).
- **Cancel** cancels the shipment in Polkurier, if the carrier still allows it. This can't be
  undone.

For shipments whose outcome is unknown, **Reconcile** looks for them in your Polkurier account
straight away.

**View order** opens the Magento order.

## Several shipments at once

Tick the shipments in either list and use the **Actions** menu:

- **Print labels**: one file with the labels of all selected shipments.
- **Print handover protocol**: one protocol for all of them.
- **Update statuses** (Shipments page).
- **Book a collective pickup** (Shipments page, see below).

Shipments that can't take part, for example refused ones, are skipped. Labels and protocols of
different Polkurier accounts (different websites) can't be combined; print them separately.

## Delivery status

With **Update shipment statuses every 30 minutes** switched on, the module follows each placed
shipment until it's finished, and you don't need to update anything by hand. A shipment counts
as finished when it's:

- cancelled;
- delivered and, with cash on delivery, the money has been paid out, settled or offset against a
  compensation;
- on its way back to you and nothing has changed for 14 days.

Finished shipments aren't checked any more, but **Update status** still works for them.

Your customers can follow the parcel from their order page: the tracking numbers link to the
carrier's tracking page.

## Collective pickups

Instead of booking a courier for each shipment, you can place shipments for a later collective
pickup (the **Handover** option when shipping) and then book one courier visit for all of them:

1. On **Polkurier > Sales > Shipments**, select the shipments (filtering by **Collective pickup**
   helps).
2. Choose **Book a collective pickup** in the **Actions** menu.
3. Enter the date and the time window, and confirm.

Only placed shipments that wait for a collective pickup and aren't booked yet are included. You'll
see which shipments were booked and, for any that weren't, the reason. Collective pickups have to
be available on your Polkurier account.

## Cancelling

**Cancel** cancels the Polkurier order, as long as the carrier hasn't collected the parcel yet.
The Magento shipment and its tracking numbers stay on the order, because Magento doesn't let a
shipment be taken back. Remove the tracking numbers by hand if you need to, and add a comment to
the order if the customer should know.

A cancelled shipment doesn't stop you from shipping the order again.

## When the Magento shipment is missing

If the Magento order couldn't be shipped when Polkurier accepted the shipment (the order was on
hold, say), the Polkurier order stands and the reason is shown under **Last error**. The module
tries again with every status update. Once whatever blocked it is out of the way, you can also
click **Create Magento shipment** to do it straight away.

If the order has already been shipped in Magento some other way, the waybills are added to its
latest shipment instead.

Next: [Cash on delivery](cash-on-delivery.md)
