<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Managing shipments

[User guide](README.md) › Managing shipments

## Where to find them

- **On the order page**, on the Shipments tab, the **Polkurier order** column, right after the
  shipment number, shows the Polkurier order each Magento shipment was sent with; click it to open
  its details.

Every Magento shipment goes with one Polkurier order, and the module links them for you: creating
a shipment with a label on Magento's page places its Polkurier order, and placing a Polkurier order
from the shipment screen creates its Magento shipment.
- **Polkurier > Sales > Shipments** lists the shipments of all orders, with filters, search and
  the collective pickup.

**Polkurier > Sales > Orders** is Magento's orders list, with only the orders placed with a Polkurier
method, so you don't have to filter Magento's list. Its **Actions**
menu is Magento's: cancel, hold and unhold, and print invoices, packing slips, credit memos, all of
these at once, or shipping labels.

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

## Shipment details

**Details** in a shipment's row opens everything about it on one page:

- the Polkurier order: number, state, carrier, shipment type, contents, when it was placed, and
  links to the Magento order and shipment;
- the delivery: status, status date, delivery date and the waybills, linked to the carrier's
  tracking page;
- the sender, the recipient (with the pickup point) and the sender on the label, if any;
- cash on delivery (amount, payout time and account, and where the money is), insurance, the
  additional services, and how the parcels are handed over, with the collection date and pickup
  number;
- the parcels, and what Polkurier charges for each item, with the total, any rebate, and whether
  it's paid and invoiced;
- the status history: every scan the carrier has reported, newest first.

The details are kept in Magento: the page reads them from Polkurier only when they aren't stored
yet, and every status update keeps them current. The scans are read again when you open the page
if they're more than an hour old and the shipment is still on its way. For a shipment Polkurier
hasn't confirmed, the page shows the order as it was sent.

The buttons at the top are the same actions as in the list (see below), and bring you back to the
details page.

## Actions on a shipment

What each row offers depends on its state.

For placed shipments:

- **Print label** downloads the shipping label, in the **Label format** you configured.
- **Print protocol** downloads the handover protocol, which the courier signs when collecting.
- **Update status** asks Polkurier for the current delivery and cash on delivery status, and the
  carrier's scans.
- **Create Magento shipment** appears when the Magento order hasn't been shipped yet (see below).
- **Cancel** cancels the shipment in Polkurier, if the carrier still allows it, and removes its
  Magento shipment so the items can be shipped again. This can't be undone.

For shipments whose outcome is unknown, **Reconcile** looks for them in your Polkurier account
straight away. When it finds an order that may be the shipment but can't be sure, it names it; if
it's the one, open the shipment's details and use **Link to a Polkurier order** with its number.

**Details** opens the shipment details page, and **View order** the Magento order.

## Several shipments at once

Tick the shipments in either list and use the **Actions** menu:

- **Print labels**: one file with the labels of all selected shipments.
- **Print handover protocol**: one protocol for all of them.
- **Update statuses** (Shipments page).
- **Book a collective pickup** (Shipments page, see below).

Shipments that can't take part, for example refused ones, are skipped.

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
Its Magento shipment is removed with it, tracking numbers and all, so the items it shipped can be
shipped again; a complete order is back to processing. A Polkurier order cancelled in the Polkurier
panel does the same once its status is next updated. The order's comments say which shipment went.
Add a comment of your own if the customer should know: Magento can't take back the shipment e-mail
they may have had.

To send the parcel again, ship the order as before. If the Magento shipment couldn't be removed
(the reason is in the log), a new Polkurier order takes it over instead: the cancelled order is
unlinked and its tracking numbers are replaced by the new waybills.

## When the Magento shipment is missing

If the Magento order couldn't be shipped when Polkurier accepted the shipment (the order was on
hold, say), the Polkurier order stands and the reason is shown under **Last error**. The module
tries again with every status update. Once whatever blocked it is out of the way, you can also
click **Create Magento shipment** to do it straight away.

If the order has nothing left to ship by then, the Polkurier order goes on the order's latest
Magento shipment without a Polkurier order. If there is none, the reason is shown under **Last
error**.

Next: [Cash on delivery](cash-on-delivery.md)
