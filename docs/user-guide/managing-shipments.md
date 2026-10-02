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
- **On the order page**, on the **Polkurier** tab, right after Shipments: every Polkurier shipment
  of the order, also the ones that weren't placed and so have no Magento shipment, with why under
  **Last error**, and what can be done with each. It has the same search, filters, columns and
  **Actions** menu as the shipments list below, for that order's shipments only.
- **On the page of a Magento shipment**, the **Polkurier order** button opens the details of the
  Polkurier order it was sent with.
- **Polkurier > Sales > Shipments** lists the shipments of all orders, newest first, with filters,
  the collective pickup and **Update statuses**. Search by keyword finds a shipment by its order
  number, Polkurier order, waybill, carrier, recipient's postcode or phone, delivery status or the
  customer's name or e-mail.

Every Magento shipment goes with one Polkurier order, and the module links them for you: creating
a shipment with a label on Magento's page places its Polkurier order, and placing a Polkurier order
from the shipment screen creates its Magento shipment.

**Polkurier > Sales > Orders** is Magento's orders list, with only the orders placed with a Polkurier
method, so you don't have to filter Magento's list. Its **Actions**
menu is Magento's: cancel, hold and unhold, and print invoices, packing slips, credit memos, all of
these at once, or shipping labels.

A shipment that failed on Polkurier's side (Polkurier down, busy or out of reach) is listed as
**Not placed**, with why and as it was filled in, to be sent again; the shipment screen and the
label options say so too. The order's next attempt places that same shipment, so it's never listed
twice. **Discard** removes it. One neither placed nor discarded gets you a notification (the bell
in the admin's header, and **System > Notifications**) a day after it was last tried, and is
removed a day later, noted in the order's history: no shipment stays **Not placed** for good.

The notifications also tell you about a shipment Polkurier didn't answer for that the automatic
check hasn't settled within an hour, and one placed without its Magento shipment for an hour. Each
links to the shipment's details, and comes once per shipment.

Actions in the lists, on the order's **Polkurier** tab and on a shipment's page take you back to
where you started them; one that removes the page you were on, like **Discard** on a shipment's
details, takes you to its order instead. One that Polkurier refused as it is (a parcel too big, say) isn't kept: you see the reason
at once, change the shipment and place it again.

## Sending again what wasn't placed

**Send again**, in the row of a shipment that wasn't placed (on the order's Polkurier tab or in
the shipments list) and on its details page, opens the shipment screen as it was filled in then,
with why it wasn't placed above it, to place it again once Polkurier is back. It's offered for the latest
attempt of an order that still has something to send: items left to ship, or a Magento shipment
without a Polkurier order.

## A Magento shipment without a Polkurier order

When an order has a Magento shipment without a Polkurier order (made before the module was
installed, through Magento's API or another integration, or while the module's shipping was turned
off), the page of that Magento shipment has an **Add a Polkurier order** button, once the order has
nothing left to ship. The shipment screen then places the
Polkurier order on that shipment, and its waybills become its tracking numbers. **Ship with
Polkurier** on the order page is only there while the order has something left to ship, next to
Magento's **Ship**.

## What the list shows

| Column | |
|---|---|
| Order | the Magento order |
| Created | when the shipment was placed |
| Carrier | the carrier the shipment goes with |
| State | see below |
| Polkurier order | the order number in your Polkurier account |
| Waybills | the carrier's tracking numbers, linked to the carrier's tracking page |
| Delivery status, Status date | the latest status reported by the carrier |
| Cash on delivery, COD status | the amount to collect and where the money is (see [Cash on delivery](cash-on-delivery.md#following-the-money)) |
| Cost (gross) | what Polkurier charged you |
| Collective pickup, Pickup booked | whether the shipment waits for a collective pickup, and whether it's booked |
| Last error | why the last action on the shipment failed, if it did |

The **State** tells you where the shipment stands with Polkurier:

| State | Meaning |
|---|---|
| Sending | being placed right now |
| Placed | Polkurier accepted it |
| Outcome unknown | Polkurier didn't confirm it; it's being checked (see [When the result is unclear](shipping-an-order.md#when-the-result-is-unclear)) |
| Not placed | it failed on Polkurier's side, or turned out not to exist; it can be sent again, with **Send again** |
| Cancelled | cancelled in Polkurier |

## Shipment details

**Details** in a shipment's row opens everything about it on one page:

- the Polkurier order: number, state, carrier, shipment type, shipment description, when it was placed, and
  links to the Magento order and shipment;
- the delivery: status, status date, delivery date and the waybills, linked to the carrier's
  tracking page;
- the sender, the recipient (with the pickup point) and the sender on the label, if any;
- cash on delivery (amount, payout time and account, and where the money is), insurance, the
  additional services, and how the parcels are handed over, with the collection date and pickup
  number;
- the order's items it shipped, with their SKUs and quantities;
- the parcels, with what each one holds, and what Polkurier charges for each item, with the total,
  any rebate, and whether it's paid and invoiced;
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
- **Print handover protocol** downloads the protocol, which the courier signs when collecting.
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

Tick the shipments in **Polkurier > Sales > Shipments**, or on an order's **Polkurier** tab, and
use the **Actions** menu:

- **Print labels**: one file with the labels of all selected shipments.
- **Print handover protocol**: one protocol for all of them.
- **Update statuses**.
- **Book a collective pickup** (see below).

Shipments that can't take part, for example refused ones, are skipped.

## Delivery status

With **Update shipment statuses on schedule** switched on, the module follows each placed
shipment until it's finished, as often as **Update shipment statuses** says, and you don't need to
update anything by hand. **Update statuses** above the shipments list updates every unfinished
shipment right away, also with the schedule switched off. A shipment counts
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

## On Magento's shipment page

The page of a Magento shipment of a Polkurier order shows, above **Payment & Shipping Method**:

- **Polkurier shipping label**, when the shipment has no Polkurier order that stands: the options
  of the one **Create Shipping Label** places for it;
- **Polkurier orders**: every Polkurier order the shipment has had, the cancelled ones included.

Its **Shipment History** notes, internally (the customer isn't notified, and it isn't shown on the
storefront), each Polkurier order that goes with the shipment, its cancellation, here or in
Polkurier, and its delivery.

## Cancelling

**Cancel** cancels the Polkurier order, as long as the carrier hasn't collected the parcel yet.
Its Magento shipment stays, with its items, but without the cancelled order's label, packages and
tracking numbers. A Polkurier order cancelled in the Polkurier panel does the same once its status
is next updated. The order's comments say which shipment was cleared.

To send the parcel again, open that Magento shipment and either:

- click **Create Shipping Label** and pack the parcels in Magento's packaging window, with the
  **Polkurier shipping label** options below; or
- click **Add a Polkurier order** to place one on the shipment screen.

Either way the new Polkurier order goes on the same Magento shipment, for its items, and its
waybills become the shipment's tracking numbers. The shipment keeps every Polkurier order it has had,
cancelled ones included (see the order's **Polkurier** tab), but never more than one that stands, so
no item is shipped twice: a shipment whose Polkurier order stands can't get another until that one
is cancelled, and a new Magento shipment can only ship what the order has left.

## When the Magento shipment is missing

If the Magento order couldn't be shipped when Polkurier accepted the shipment (the order was on
hold, say), the Polkurier order stands and the reason is shown under **Last error**. The module
tries again with every status update. Once whatever blocked it is out of the way, you can also
click **Create Magento shipment** to do it straight away.

If the order has nothing left to ship by then, the Polkurier order goes on the order's latest
Magento shipment without a Polkurier order. If there is none, the reason is shown under **Last
error**.

Next: [Cash on delivery](cash-on-delivery.md)
