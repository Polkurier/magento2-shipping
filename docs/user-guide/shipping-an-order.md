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
| Parcels | packed in Magento's packaging window, item by item | typed in or taken from pack templates |
| Label | stored with the Magento shipment (**Print Shipping Label**) | printed from the Polkurier shipments |
| Recipient | the order's shipping address, as it is | can be edited before placing |
| Prices before placing | no | every carrier's price, updated as you go |
| Partial shipments | yes, choose the quantities to ship | no, the whole order is shipped |

Either way the result is the same: a Polkurier order, a shipped Magento order with the waybill
numbers as tracking numbers, and a comment on the order with the Polkurier order number.

Both work only for orders placed with a Polkurier delivery method. An order placed with another
carrier's method shows no Polkurier buttons or sections, and is left to that carrier.

## What every shipment starts with

Both ways fill the shipment in from the order and your
[defaults](configuration.md#shipments):

- the carrier the customer chose;
- your default sender address, or the store's address or shipping origin, if you let those be
  used;
- the recipient from the order's shipping address, with the street split into street, house
  number and flat number;
- the pickup point the customer chose, if any;
- the items packed into parcels of your pack templates, when every product shipped has its
  shipping dimensions (see [Packing automatically](#packing-automatically)) and **Pack new
  shipments automatically** is on; otherwise one parcel of your default pack template, or no
  parcel if you haven't marked a template as the default. Parcels of a template weigh what
  **Parcel weight** says (see [Configuration](configuration.md#parcels)); a parcel you add without
  a template starts with the weight of the order's products, when known;
- cash on delivery for the order total, if the order was paid with one of your cash on delivery
  payment methods, paid out the way you configured to your default bank account (or, if none is
  marked as the default, the first one by name). When the order ships in parts, it's only what
  the order's other Polkurier orders don't collect yet (see
  [Shipping an order in parts](#shipping-an-order-in-parts));
- insurance and additional services according to your defaults, limited to what the carrier
  offers. Insurance is for the order total, or for what the items are worth when only part of the
  order is shipped;
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
   without one. Fix those first, or Polkurier will refuse the shipment. The cash on delivery line
   also turns red when it collects more than is left to collect on the order.
5. Choose the quantities to ship, as usual in Magento. **Create Shipping Label** at the bottom of
   the page is already ticked, so the shipment is created with its Polkurier order. If you untick
   it, Magento creates a plain shipment and nothing is sent to Polkurier.
6. Click **Submit Shipment**. Magento's packaging window opens. Add a package for each parcel,
   put the items in it and enter its weight and size. The weight and size start in kilograms and
   centimetres. As the package type, pick a parcel type (standard, non-standard, long, half
   pallet, pallet) and enter the size. To save typing, pick one of your pack templates in the
   package's **Pack template…** list: it fills in the parcel type and size, and the weight by
   **Parcel weight** (see [Configuration](configuration.md#parcels)), again as you add items. You
   can still change any of them.
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

## Shipping an order in parts

To send an order in several parcels on different days, ship each part with only the quantities
that go out now: on Magento's **New Shipment** page, or under **Items to ship** on the Polkurier
shipment screen. Each part becomes a Magento shipment with its own Polkurier order, and the order
page's **Shipments** tab shows which Polkurier order each one was sent with. **Ship with
Polkurier** stays on the order page while anything is left to ship.

- **Cash on delivery** starts from what's left to collect: the order total, less what the order's
  other Polkurier orders collect. The window shows how much that is. Say a 250 zł order goes out in
  two parts and the first collects 150 zł: the second starts at 100 zł, and if the first collects
  it all, the second starts without cash on delivery. Cancelled and refused Polkurier orders don't
  count. One Polkurier hasn't confirmed does, until you reconcile it.
- **Insurance**, when it's on, is for what the items in the shipment are worth, with tax and after
  discounts, and changes as you change the quantities. Once you set the amount yourself it stays.
  For the whole order at once, it's the order total, as before. The insurance window shows what the
  shipment is worth.

The Polkurier shipment screen starts from what's left to collect in the same way, and warns you,
without stopping you, when the cash on delivery is more than that.

### Before your first label

Magento checks a few things of its own before it creates any label, for any carrier:

- the store's name and phone number (**Stores > Configuration > General > General > Store
  Information**);
- the shipping origin (**Stores > Configuration > Sales > Shipping Settings > Origin**);
- a first and last name on your admin user account.

Polkurier uses your sender address rather than these, but Magento won't create a label without
them.

## On the Polkurier shipment screen

This is the way to go when you want to see everything at once, compare carriers or correct the
recipient.

Click **Ship with Polkurier** on the order page. (If you turned off labels on Magento's shipment
page, **Ship** opens this screen too, for orders placed with a Polkurier method.)

The form is on the left, in five sections; the pack templates and the button that places the
order are on the right. Each section has a **Change** link or button where there's more to set,
which opens the details in a window. In a window, **Done** checks what you entered and closes it;
**Cancel** puts back what was there before.

### Shipment

- **Carrier**, with **Change**: a window lists every carrier on your account with what Polkurier
  would charge you for this shipment as it stands, net and gross, cheapest first. The cheapest one
  is marked, and badges show which carriers deliver to pickup points (**Points**, or **Points
  only**) and which collect cash on delivery (**COD**). Carriers that can't take the shipment as
  it is are tucked away; **Show … more** lists them too. Type in the search box to find a carrier
  by name or code, and click one to choose it.
- **Contents**, with **Change**: the description printed on the label, and the shipment type.
- **Price**: what the chosen carrier charges, net and gross. This is Polkurier's price to you, not
  what the customer paid for shipping. It updates on its own a moment after you change anything
  that affects it, once every parcel has its size and weight.
- **Parcels**: one line per parcel, with its size and weight, what it holds, and **Edit** and
  **Remove**. **Add a parcel** lets you start from one of your pack templates or enter the size
  yourself; either way, the parcel's window opens before the parcel is added, with a template's
  type, size and weight filled in. In the parcel's window, **What it holds** lists the items being
  shipped: enter how many of each go in this parcel. Until you type a weight yourself, the parcel's
  weight follows **Parcel weight**: what they weigh, or the template's weight. You're warned when a
  parcel is lighter than what's in it. Below the list, you see what no parcel holds yet. Saying what each
  parcel holds is optional; when you do, Magento's shipment keeps it as its packages, so **Show
  Packages** on the Magento shipment shows what went in which parcel.
- **Carrier-specific fields**: extra information some carriers ask for, such as a reference
  number, right under the parcels. Only shown for such carriers.

When you switch carriers, the rest of the form adapts: services the carrier doesn't offer are
dropped, dropping the parcels off at a point disappears for carriers that don't take parcels that
way, and for a carrier that delivers only to pickup points, the pickup point becomes required.

### Packing automatically

Products have a **Shipping dimensions** group on their edit page: the length, width and height in
centimetres of one unit as it's shipped, packaging included. Together with the product's weight,
they let the module pack the items into parcels for you:

- The aim is as few parcels as possible. If one template takes everything, that's the parcel.
  Otherwise the template that takes the most makes the first parcel, and the rest is packed the
  same way. Parcels whose contents fit one template together are then put together.
- Only templates of the shipment's type are used, so a shipment of boxes isn't put on a pallet.
- Room means actual space: an item only goes where there's a gap big enough for it, not wherever
  enough volume is left. A 15 × 10 × 10 cm item doesn't go into a 50 × 40 × 30 cm box that already
  holds a 45 × 35 × 25 cm one, since only 5 cm are free around it. Items are turned whichever way
  fits.
- Each parcel then gets the smallest template its items can be packed into.
- An item that fits no template gets a parcel of its own size.
- A parcel weighs what **Parcel weight** says: what's in it, or its template's weight. A parcel
  with a product that has no weight takes its template's weight, and **Pack automatically** names
  the products without one.
- A parcel heavier than its template's **Maximum weight** is pointed out in the shipment
  section, whether it was packed automatically or by hand. By default that's all; with **Pack
  template weight limits** set to keep parcels under them, the packing splits parcels or takes a
  bigger template to stay under the limit. An item too heavy for every template still goes into
  the box it fits, and is pointed out.

If your products can turn out a little bigger than their dimensions say, set a **Packing margin**
(see [Configuration](configuration.md)): it's added to every dimension of every product before
packing, so with 1 cm a 12 × 10 × 8 cm product is packed as 13 × 11 × 9 cm.

The result is an estimate, and the screen says so under parcels packed this way. Every product
counts as a box of its shipping dimensions, whatever its real shape, so a lamp is packed like a
cuboid around it. The packing also doesn't try every arrangement, so now and then it uses a bigger
box, or one more, than a person would. Check the parcels before you place the order. New shipments start packed this way when every product shipped has its dimensions and you
have a pack template with a size, unless you switch **Pack new shipments automatically** off in the
configuration. **Pack automatically** in the parcels does it again, for example
after you change the quantities to ship; it replaces the parcels there are, once you confirm. If a
product has no dimensions, the screen tells you which.

For a configurable product, the dimensions of the option ordered count, or the product's own when
the option has none. A bundle shipped together is packed as one box: its own dimensions if it has
them, otherwise its parts stacked one on another, lying flat. That box is as long and as wide as
the largest part and as high as all of them together, so give the bundle its own dimensions if
its parts pack tighter than that.

### Items to ship

Every item the order still has to ship, with how many were ordered and shipped. The quantity to
ship starts at everything that's left. Lower it, or set it to 0, to ship the order in parts (see
[Shipping an order in parts](#shipping-an-order-in-parts)); **Ship everything left** puts
everything back. Below the list you see what the shipment is worth, and the insurance follows it
until you change the insurance yourself. The Magento shipment carries only these quantities, and
the rest stays for the next shipment.

You can't ship more than is left, or nothing at all: the screen tells you before you place the
order, and the order isn't sent.

When everything is shipped already, the new Polkurier order goes on a Magento shipment that has no
Polkurier order yet, such as one created without a label: the list gives way to a note saying
which shipment. A cancelled Polkurier order normally takes its Magento shipment with it, so its
items are simply left to ship. If every Magento shipment of the order has a Polkurier order that
isn't cancelled, there's nothing to send: cancel the one you want to replace first.

### Additional services

Switches for cash on delivery, insurance, the services the chosen carrier offers, and **Another
sender on the label** (a cover address), if you have cover addresses.

- Switching **Cash on delivery** on fills in what's left to collect on the order (the order
  total, unless its other Polkurier orders already collect part of it) and opens its window, where
  you can change the amount, the payout time, where it's paid out and the bank account. Only shown
  for carriers that collect cash, unless the order already has cash on delivery.
- Switching **Insurance** on insures the shipment for what it carries (the order total when
  nothing has been shipped yet), or for the cash on delivery amount if that's higher; **Change**
  sets another value.

### Sender

The address the parcels go out from, with **Change** to pick another of your sender addresses.
Without one chosen, the store's address is used, if the configuration allows it (see **Fall back to
the store's address** on the [configuration page](configuration.md#shipments)).

Next to it, how the parcels get to the carrier:

- **A courier collects them**: the collection dates and time windows the carrier offers for your
  sender address are read on their own, and the first free one is chosen; pick another from the
  lists if you like.
- **I drop them off at a point**, for carriers that take parcels that way.
- **Collect later, together with other shipments**: a collective pickup, booked later from the
  shipments list (see [Managing shipments](managing-shipments.md)).

### Recipient

The recipient's name, address, phone and e-mail, with **Change** to correct them. For carriers that
deliver to pickup points, the point the customer chose is shown with its address; **Change** or
**Choose on map** opens the map.

### Before you place it

While you fill in the form, only softer warnings show, quietly, in the section they're about: cash
on delivery above what's left to collect, or a parcel lighter than what's in it, say. Polkurier's
current messages about the chosen carrier, such as delays or extra fees, are shown under the
carrier.

When you place the order with something missing, the order isn't sent. Each section with a
problem is marked in red, with what's missing under its title, and the page scrolls to the first.
Examples are a missing pickup point, collection date or parcel size. Where the fix is in a window,
**Fix** opens it; fields such as the collection date are marked themselves. The marks go away as
you fix things.

### Pack templates and placing the order

On the right are your pack templates. Clicking one sets the last parcel to it, or adds a parcel
when there's none; **A parcel without a template** adds one you fill in yourself. A template for
another shipment type replaces the parcels you have, once you confirm. If no template is marked as
the default for new shipments, a note links to the templates page.

Below them are the price and, if another carrier would be cheaper, a **Switch** link.

Click **Place the Polkurier order** at the top of the page. If a field isn't
filled in correctly, the window holding it opens so you can see what's wrong.

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
