<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Shipping an order

[User guide](README.md) › Shipping an order

There are two ways to ship an order with Polkurier. Use whichever suits the order at hand; both
can be switched on at the same time (see [Configuration](configuration.md#shipments)).

| | Magento's shipment page | Polkurier shipment screen |
|---|---|---|
| Opened with | **Ship** on the order page | **Ship with Polkurier** on the order page |
| Works for | orders placed with a Polkurier method | any order |
| Parcels | packed in Magento's packaging window, item by item | typed in or taken from pack templates |
| Label | stored with the Magento shipment (**Print Shipping Label**) | printed from the Polkurier shipments |
| Recipient | the order's shipping address, as it is | can be edited before placing |
| Prices before placing | no | every carrier's price, updated as you go |
| Partial shipments | yes, choose the quantities to ship | no, the whole order is shipped |

Either way the result is the same: a Polkurier order, a shipped Magento order with the waybill
numbers as tracking numbers, and a comment on the order with the Polkurier order number.

## What every shipment starts with

Both ways fill the shipment in from the order and your
[defaults](configuration.md#shipments):

- the carrier the customer chose, or your default carrier for orders placed with another method;
- your default sender address, or the store's address or shipping origin, if you let those be
  used;
- the recipient from the order's shipping address, with the street split into street, house
  number and flat number;
- the pickup point the customer chose, if any;
- one parcel of your default pack template;
- cash on delivery for the order total, if the order was paid with one of your cash on delivery
  payment methods, paid out the way you configured to your default bank account;
- insurance and additional services according to your defaults, limited to what the carrier
  offers;
- the handover: a courier pickup, or dropping the parcels off yourself where the carrier prefers
  that.

## On Magento's shipment page

This is the way to go when you want Polkurier to behave like any other carrier in Magento.

1. Click **Ship** on the order page. Magento's **New Shipment** page opens.
2. Below the payment and shipping information you'll find the **Polkurier shipping label**
   section, with a one-line summary of each group of options:
   - **Carrier**: the carrier, the shipment type and the sender address;
   - **Pickup point**, for carriers that deliver to points;
   - **Cash on delivery**, for carriers that offer it;
   - **Insurance and services**;
   - **Handover**: a courier pickup (optionally on a given day, within a time window), dropping
     the parcels off yourself, or a courier pickup booked later for several shipments at once.
3. Click **Change** next to a line to edit that group in a small window, then **Apply**. The
   pickup point can be typed in as a code or chosen with **Choose on map**; the collection date
   and times only appear for a courier pickup. If you
   switch to another carrier, whatever that carrier doesn't offer is dropped from the other
   groups, and the shipment type is changed if the new carrier doesn't take it.
4. A line turns red when something the carrier needs is missing: a pickup point for carriers
   that deliver only to points, or a collection date for carriers that won't send a courier
   without one. Fix those first, or Polkurier will refuse the shipment.
5. Choose the quantities to ship, as usual in Magento, and tick **Create Shipping Label** at the
   bottom of the page. The Polkurier section only matters with this box ticked; without it,
   Magento creates a plain shipment and nothing is sent to Polkurier.
6. Click **Submit Shipment**. Magento's packaging window opens. Add a package for each parcel,
   put the items in it and enter its weight and size. The weight and size start in kilograms and
   centimetres. As the package type, pick a parcel type (standard, non-standard, long, half
   pallet, pallet) and enter the size. To save typing, pick one of your pack templates in the
   package's **Pack template…** list: it fills in the parcel type, size and weight, and you can
   still change any of them.
7. Click **Save** in the packaging window.

All packages of one shipment become the parcels of a single Polkurier order. When Polkurier
accepts it:

- the Magento shipment is created with the waybills as tracking numbers, titled with the shipping
  method's name;
- the label is stored with the shipment, so **Print Shipping Label** on the shipment page gives
  you the Polkurier label, and Magento's **Print Shipping Labels** action in the orders and
  shipments lists includes it;
- the shipment also appears among the order's Polkurier shipments, where you can print its
  protocol, follow it and cancel it (see [Managing shipments](managing-shipments.md)).

If Polkurier hasn't issued the label yet, the shipment is still created, with its tracking
numbers, and you're told the label isn't ready. Print it later from the Polkurier shipments of the
order, or open the Magento shipment and use **Create Shipping Label** there: for a shipment that
already has a Polkurier order, that fetches the label instead of placing a new order.

If Polkurier refuses the shipment, the reason appears in the packaging window and nothing is
created, so you can close the window, change the options or packages and try again.

### Before your first label

Magento checks a few things of its own before it creates any label, for any carrier:

- the store's name and phone number (**Stores > Configuration > General > General > Store
  Information**);
- the shipping origin (**Stores > Configuration > Sales > Shipping Settings > Origin**);
- a first and last name on your admin user account.

Polkurier uses your sender address rather than these, but Magento won't create a label without
them.

## On the Polkurier shipment screen

This is the way to go when you want to see everything at once, compare carriers, correct the
recipient, or ship an order placed with another shipping method.

Click **Ship with Polkurier** on the order page. (If you turned off labels on Magento's shipment
page, **Ship** opens this screen too, for orders placed with a Polkurier method.)

The screen has three columns, so a whole shipment fits on one page.

### Carriers, with their prices

On the left is every carrier on your account, with what Polkurier would charge you for this
shipment as it stands, cheapest first. The cheapest one is marked, and badges show which carriers
deliver to pickup points (**Points**, or **Points only**) and which collect cash on delivery
(**COD**). Carriers that can't take the shipment as it is are tucked away; **Show … more** lists
them too. Type in the search box to find a carrier by name or code.

The prices update on their own a moment after you change anything that affects them: the parcels,
cash on delivery, insurance, services, the handover or the recipient's address. Click a carrier to
choose it.

When you switch carriers, the rest of the screen adapts: services the carrier doesn't offer are
dropped, the handover becomes a courier pickup if the carrier doesn't take parcels dropped off at
a point, and the cards below show what it needs. For a carrier that delivers only to pickup
points, the pickup point becomes a required field.

Prices need the size and weight of every parcel; until they're filled in, the list says so and
shows every carrier. A courier pickup is priced the same whatever day you choose, so prices show
before you pick the collection date.

### The shipment, in cards

The middle column sums up the shipment in cards, each with a **Change** button that opens the
details in a window:

- **Recipient**: name, address, phone and e-mail, and the pickup point. For carriers that deliver
  to points, **Choose a point on the map** opens the map straight away.
- **Shipment and sender**: the shipment type, the description and the sender address.
- **Handover**: a courier pickup, dropping the parcels off at a point, or a later collective
  pickup. For a courier pickup, the window can show the collection dates and time windows
  available for your sender address; click one to use it.
- **Cash on delivery**: the amount, the payout time, where it's paid out and the bank account.
  Leave the amount empty for none. Only shown for carriers that collect cash, unless the order
  already has cash on delivery.
- **Insurance and services**: the declared value, the additional services the carrier offers,
  and a cover address.
- **Carrier-specific fields**: extra information some carriers ask for, such as a reference
  number. Only shown for such carriers.

In each window, **Done** checks what you entered and closes it; **Cancel** puts back what was
there before.

Below the cards are the **Parcels**, edited right on the page: one row per kind of parcel, with its
type, size, weight and quantity. Pick a template to fill a row in, and **Add a parcel** for
another.

A card with a red top edge needs attention: something the carrier needs is missing.

### The summary

The right column shows the chosen carrier and its price, gross and net. This is Polkurier's price
to you, not what the customer paid for shipping. If another carrier would be cheaper, it says so,
with a **Switch** button.

Under **Before you place it** you'll find anything that would stop the shipment, such as a missing
pickup point, collection date or parcel size, each with a **Fix** button that takes you to the
right place. When nothing is missing, it says so.

Click **Place the Polkurier order**, here or at the top of the page. If a field isn't filled in
correctly, the window holding it opens so you can see what's wrong.

When Polkurier accepts the shipment:

- the Magento order is shipped, with the waybill numbers as tracking numbers;
- a comment with the Polkurier order number is added to the order;
- the customer gets Magento's shipment e-mail, if **Send the shipment e-mail to the customer** is
  on.

If Polkurier refuses the shipment (a missing phone number, a parcel too big for the carrier, a
date the courier doesn't come on), you'll see the reason, with the fields it's about. The screen
keeps everything you entered, so you can fix it and try again.

If the Magento order can't be shipped at that moment, for example because it's on hold, the
Polkurier order still stands. The shipment shows the reason under **Last error**, and you can
create the Magento shipment later (see
[Managing shipments](managing-shipments.md#when-the-magento-shipment-is-missing)).

## When the result is unclear

Occasionally Polkurier doesn't give a clear answer, for example when the connection drops at the
wrong moment. The shipment may or may not have been placed. The module never simply sends it
again, because that could create a second shipment you'd pay for, and it won't let you place
another one for the same order until this is settled.

Instead, the shipment is marked **Outcome unknown**, and the module finds out on its own: every
10 minutes it looks for such shipments in your Polkurier account. You can also click
**Reconcile** in the shipment's row to check straight away.

- **A matching shipment is found**: it's linked to the order as if it had gone through normally,
  and the Magento order is shipped.
- **None is found** 15 minutes after the attempt: the shipment is marked **Not placed**, and you
  can ship the order again. Before those 15 minutes are up, it may simply not be listed yet, so
  the module keeps waiting.
- **Several are found**: the same shipment went through more than once. The earliest is linked,
  and the others are named in a comment on the order and under **Last error**, so you can check
  them in the Polkurier panel and cancel the ones you don't need.

On Magento's shipment page, an unclear result means no Magento shipment is created yet; the
packaging window tells you so. Once the Polkurier order is found, the order is shipped with its
waybills automatically. Don't create the label again in the meantime.

Magento's cron must be running for the automatic check.

Next: [Managing shipments](managing-shipments.md)
