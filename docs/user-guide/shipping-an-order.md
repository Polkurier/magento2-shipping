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
| Label | stored with the Magento shipment (**Print Shipping Label**) | printed from the Polkurier shipment |
| Recipient | the order's shipping address, as it is | can be edited before placing |
| Prices before placing | no | every carrier's price, updated as you go |
| Partial shipments | yes, choose the quantities to ship | yes, under **Items to ship** |

Either way the result is the same: a Polkurier order, a shipped Magento order with the waybill
numbers as tracking numbers, and a comment on the order with the Polkurier order number.

Both work only for orders placed with a Polkurier delivery method. An order placed with another
carrier's method shows no Polkurier buttons or sections, and is left to that carrier.

## What every shipment starts with

Both ways fill the shipment in from the order and your
[defaults](configuration.md#shipments):

- the carrier the customer chose;
- your default sender address, or the store's address or shipping origin, if you let those be
  used; with none of these, a new sender address to fill in, saved to your addresses once the
  shipment is placed (see [Typing in a sender, an account or a parcel](#typing-in-a-sender-an-account-or-a-parcel));
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
  marked as the default, the first one by name; with no bank account at all, a new one to type
  in). When the order ships in parts, it's only what
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
   - **Carrier**;
   - **Shipment description**: what's printed on the label, the order number to start with;
   - **Sender**: the address the parcels are sent from;
   - **Handover**: a courier pickup (optionally on a given day, within a time window), dropping
     the parcels off yourself, or a courier pickup booked later for several shipments at once;
   - **Pickup point**, for carriers that deliver to points;
   - **Cash on delivery**, for carriers that offer it;
   - **Insurance**, for carriers that insure;
   - **Additional services**.
3. Click **Change** next to a line to edit that group right there, then **Apply** (or press Enter);
   **Cancel** (or Escape) leaves it as it was. One group is edited at a time. The
   pickup point can be typed in as a code or chosen with **Choose on map**; the collection date
   and time window only appear for a courier pickup, chosen from those Polkurier offers for the
   carrier, the sender and the recipient (a day without windows means any time that day). The
   additional services listed are all those any of your carriers offers, so you can choose what
   you need first; if the chosen carrier doesn't offer one, the label can't be created until you
   switch carriers or untick it. If you switch to another carrier, cash on delivery, insurance and
   other options it doesn't offer are dropped from the other groups. The pickup point
   belongs to the carrier it was chosen for: it's cleared when you switch, and comes back if you
   switch back.
4. A line turns red when something the carrier needs is missing: a pickup point for carriers
   that deliver only to points, or a collection date for carriers that won't send a courier
   without one. The cash on delivery line also turns red when it collects more than is left to
   collect on the order, and the insurance line when the carrier needs the shipment insured for
   at least the cash on delivery. Until they're fixed, the packaging window doesn't open: you're
   told what to fix instead.
5. Choose the quantities to ship, as usual in Magento. The shipment is always created with a
   shipping label, which places its Polkurier order: Magento's **Create Shipping Label** box is
   ticked and kept that way. If Polkurier refuses, nothing is shipped: correct what it names and
   submit again. Without a Polkurier order, there's no shipment.
6. Click **Submit Shipment**. Magento's packaging window opens. Add a package for each parcel,
   put the items in it and enter its weight and size. The weight and size start in kilograms and
   centimetres. As the package type, pick a parcel type (standard, non-standard, long, envelope,
   or a pallet by its size, from a quarter pallet to 150 × 150) and enter the size. To save typing, pick one of your pack templates in the
   package's **Pack template** column, before **Type**: it fills in the parcel type and size, and
   the weight by **Parcel weight** (see [Configuration](configuration.md#parcels)), again as you
   add items. A new package starts with your default template already filled in. You can still
   change any of them.

   With **Pack new shipments automatically** on (see [Configuration](configuration.md#parcels)),
   the window opens with the items already packed into your pack templates, as the Polkurier
   shipment screen packs them, and says so above the packages. **Pack automatically**, by
   **Add Package**, packs them again in place of the packages there are, for example after you
   changed them; packages holding items keep the shipment to their kind. Check the actual sizes
   and weights before saving.
7. Click **Save** in the packaging window.

The packages make the shipment's type: all envelopes go as an envelope shipment, all pallets as a
pallet shipment, anything else as parcels. Parcels, envelopes and pallets can't go in one shipment,
and not every carrier takes envelopes or pallets; either way, you're told when you save, before
anything is sent, and change the packages or the carrier.

All packages of one shipment become the parcels of a single Polkurier order. When Polkurier
accepts it:

- the Magento shipment is created with the waybills as tracking numbers, titled with the shipping
  method's name;
- the label is stored with the shipment, so **Print Shipping Label** on the shipment page gives
  you the Polkurier label, and Magento's **Print Shipping Labels** action in the orders and
  shipments lists includes it;
- the Polkurier shipment opens from the **Polkurier order** column on the order's Shipments tab,
  where you can print its protocol, follow it and cancel it (see
  [Managing shipments](managing-shipments.md)).

If Polkurier hasn't issued the label yet, the shipment is still created, with its tracking numbers,
and you're told the label isn't ready. Print it later from the Polkurier shipment (the **Polkurier
order** column on the order's Shipments tab), or open the Magento shipment and use **Create
Shipping Label** there: for a shipment that already has a Polkurier order, that fetches the label
instead of placing a new order.

If Polkurier refuses the shipment, the reason appears in the packaging window and nothing is
created, so you can close the window, change the options or packages and try again.

## Typing in a sender, an account or a parcel

You don't need anything stored in **Polkurier > Settings** to ship. On both screens:

- the sender can be **A new address**: fill it in where you choose it (Polkurier needs all but the
  company and the flat number). It's used for this shipment, and with **Save to your addresses for
  later shipments**, saved to your [addresses](master-data.md#addresses) under the name you give,
  or the company or name and the city;
- cash on delivery paid out to a bank account can go to **A new bank account**: type its number
  in, and tick **Save to your bank accounts for later shipments** to keep it. Polkurier takes a
  saved bank account once you confirm it with the code or your security PIN (see
  [Bank accounts](master-data.md#bank-accounts)); this shipment's cash on delivery goes to the
  number either way;
- a parcel without a pack template can be saved as one: tick **Save as a pack template for later
  shipments** in the parcel's window on the Polkurier shipment screen, or **Save as a pack
  template** under **No template** in Magento's packaging window. Unnamed, it's called after its
  type and size.

Whatever you ask to save is saved only once Polkurier accepts the shipment, and sent to your
Polkurier account like anything you add in the settings; a refused shipment saves nothing. The
first of each becomes the default. A message says what was saved, and what wasn't and why.

## When you ship with another carrier or point

Whichever way you ship, you can send the parcels with another carrier or to another pickup point
than the customer chose. Once the Polkurier order is placed, the order follows it: its shipping
method and its **Shipping & Handling Information**, in the order list too, show the carrier and
point the parcels went with, so the order tells what was actually sent. The shipping amount the
customer paid doesn't change.

What the customer chose is kept: the order page shows it under **Chosen by the customer**, and the
order's history says what was changed. If you cancel that Polkurier order and the order has no
other placed one, the customer's choice comes back.

## Shipping an order in parts

To send an order in several parcels on different days, ship each part with only the quantities
that go out now: on Magento's **New Shipment** page, or under **Items to ship** on the Polkurier
shipment screen. Each part becomes a Magento shipment with its own Polkurier order, and the order
page's **Shipments** tab shows which Polkurier order each one was sent with. **Ship with
Polkurier** stays on the order page while anything is left to ship.

- **Cash on delivery** collects what the items in the shipment are worth, with tax and after
  discounts, and changes as you change the quantities. The shipment that carries everything still
  left to ship collects everything left to collect: the order total, less what the order's other
  Polkurier orders collect, so the shipping is paid with the last part. No shipment collects more
  than is left; the window shows how much that is. Say a 250 zł order of a 150 zł and an 85 zł item
  with 15 zł shipping goes out in two parts: the first collects 150 zł, the second the 100 zł left.
  Once you set the amount yourself it stays. Cancelled and refused Polkurier orders don't count.
  One Polkurier hasn't confirmed does, until you reconcile it.
- **Insurance**, when it's on, is for what the shipment collects with cash on delivery, so the
  parts' insurance adds up to the order total just as their cash on delivery does. Without cash on
  delivery, it's what the items in the shipment are worth, with tax and after discounts, and the
  shipping and handling for the part that carries the rest of the order; the whole order at once is
  insured for its total. It changes as you change the quantities or the cash on delivery. Once you
  set the amount yourself it stays.
- **Include shipping and handling in cash on delivery and insurance**: for a shipment of part of
  the order, adds the shipping and handling the customer was charged to what the part collects and
  is insured for, e.g. to collect it with the first part rather than the last. It starts unticked,
  and is unticked again whenever the quantities go from the whole order to part of it; ticking or
  unticking it works both amounts out again. The whole order always includes it, so it isn't
  offered then. It's on the Polkurier shipment screen, under **Shipment value**; on Magento's New
  Shipment page, a part always goes without it.

The Polkurier shipment screen works out both in the same way as you change **Items to ship**, and
warns you, without stopping you, when the cash on delivery is more than is left to collect.

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

Click **Ship with Polkurier** on the order page, next to **Ship**. (If you turned off labels on
Magento's shipment page, there's no separate button: **Ship** opens this screen instead, for orders
placed with a Polkurier method.) A shipment that wasn't placed opens it with **Send again**, as it
was filled in then (see [Managing shipments](managing-shipments.md#sending-again-what-wasnt-placed)).

The form is on the left, in five sections, **Shipment** and **Items to ship** side by side; the parcels and the price are on the right (below
the form on a narrow screen), and **Place the Polkurier order** is at the top of the page. Each
section has a **Change** link or button where there's more to set, which opens the details in a
window. In a window, **Done** checks what you entered and closes it; **Cancel** puts back what was
there before.

### Shipment

- **Carrier**, with **Change**: a window lists the carriers you work with (see **Available
  carriers**) with what Polkurier would charge you for this shipment as it stands, net and gross,
  cheapest first. The cheapest one is marked, and badges show which carriers deliver to pickup
  points (**Points**, or **Points only**) and which collect cash on delivery (**COD**). Carriers
  that can't take the shipment as it is are tucked away; **Show … more** lists them too. Type in
  the search box to find a carrier by name, and click one to choose it.
- **Shipment description**, with **Change**: what's printed on the label, the order number to
  start with (see **Shipment description** in [Configuration](configuration.md)). The shipment's type, shown
  next to the carrier, is what its parcels are: envelopes, pallets, or parcels.
- **Price**: what the chosen carrier charges, net and gross. This is Polkurier's price to you, not
  what the customer paid for shipping. It updates on its own a moment after you change anything
  that affects it, once every parcel has its size and weight. If Polkurier refuses to price the
  shipment, the reason shows in red; **Try again** shows too when the failure may pass by itself
  (no connection, Polkurier down or busy), but not when the shipment itself is refused: change
  what the reason names instead, and the price is asked for again.
- **Carrier-specific fields**: extra information some carriers ask for, such as a reference
  number, right under the price. Only shown for such carriers.

When you switch carriers, the rest of the form adapts: dropping the parcels off at a point disappears for carriers that don't take parcels that
way, and for a carrier that delivers only to pickup points, the pickup point becomes required.

### Packing automatically

Products have a **Shipping dimensions** group on their edit page: the length, width and height in
centimetres of one unit as it's shipped, packaging included. Together with the product's weight,
they let the module pack the items into parcels for you:

- The aim is as few parcels as possible. If one template takes everything, that's the parcel.
  Otherwise the template that takes the most makes the first parcel, and the rest is packed the
  same way. Parcels whose contents fit one template together are then put together.
- Only templates of one kind are used, so a shipment never mixes parcels, envelopes and pallets.
  With parcels in the list, it's their kind. Without any, as for a new shipment, it's the kind set
  in **Parcels go as** on the configuration page (parcels by default), or the first kind the
  carrier takes when it doesn't take that one. An item too big for every template of the kind
  gets one of its own size of the same kind: a parcel, an envelope, or the smallest pallet it
  stands on. So one long item doesn't put the order on a pallet; the prices then show which
  carriers take that parcel.
- Room means actual space: an item only goes where there's a gap big enough for it, not wherever
  enough volume is left. A 15 × 10 × 10 cm item doesn't go into a 50 × 40 × 30 cm box that already
  holds a 45 × 35 × 25 cm one, since only 5 cm are free around it. Items are turned whichever way
  fits.
- Each parcel then gets the smallest template its items can be packed into.
- An item that fits no template gets a parcel of its own size.
- A parcel weighs what **Parcel weight** says: what's in it, or its template's weight. A parcel
  with a product that has no weight takes its template's weight, and **Pack automatically** names
  the products without one. A parcel of its own size has no template weight to take, so it's
  left without a weight, and you're told to enter it.
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
box, or one more, than a person would. Check the parcels before you place the order. New shipments
start packed this way when every product shipped has its dimensions and you have a pack template
with a size, unless you switch **Pack new shipments automatically** off in the configuration.
**Pack automatically** in the parcels does it again, for example after you change the quantities to
ship. It asks you to confirm first, and replaces any parcels there are. If a product has no
dimensions, the screen tells you which.

For a configurable product, the dimensions of the option ordered count, or the product's own when
the option has none. A bundle shipped together is packed as one box: its own dimensions if it has
them, otherwise its parts stacked one on another, lying flat. That box is as long and as wide as
the largest part and as high as all of them together, so give the bundle its own dimensions if
its parts pack tighter than that.

### Items to ship

Beside the **Shipment** section: every item the order still has to ship, with how many were ordered and shipped. The quantity to
ship starts at everything that's left. Lower it, or set it to 0, to ship the order in parts (see
[Shipping an order in parts](#shipping-an-order-in-parts)). In the **Shipment** section, under the price, are the totals: what the products in the shipment are worth (with
tax, after discounts), what the customer was charged for shipping and handling, the order total,
and the shipment value, which is one of the two: the order
total when all of the order ships, the products when only part of it does. The insurance follows the
shipment value until you change the insurance yourself. The Magento shipment carries only these quantities, and
the rest stays for the next shipment.

You can't ship more than is left, or nothing at all: the screen tells you before you place the
order, and the order isn't sent.

When everything is shipped already, the new Polkurier order goes on a Magento shipment that has no
Polkurier order that stands, such as one created without a label or one whose Polkurier order was
cancelled: the list gives way to a note saying which shipment. Opened with **Add a Polkurier order**
from a Magento shipment's page, the screen places the order on that shipment, for its items. If
every Magento shipment of the order has a Polkurier order that isn't cancelled, there's nothing to
send: cancel the one you want to replace first.

### Additional services

Switches for cash on delivery, insurance, the services there are for the shipment's type (what any
of your carriers offers for parcels, envelopes or pallets), and **Another sender on the label**, if
you have addresses marked **Can be a cover address**. Switched on, it lets you pick which of them
is printed on the label as the sender. Choose the services you need first: one the chosen carrier
doesn't offer is marked, the carrier gets a problem with **Fix**, and the carrier window tucks such
carriers away, saying what each doesn't offer. Pallet shipments also list the services for pallets (dangerous goods, do not
stack, tail lift and side loading or unloading at collection or delivery). Polkurier doesn't say
which carriers take those, so they're offered with every carrier that takes pallets, and the prices
show which carriers can do it: those that can't are tucked away.

- Switching **Cash on delivery** on fills in what's left to collect on the order (the order
  total, unless its other Polkurier orders already collect part of it); **Change** opens its
  window, where you can change the amount, the payout time, where it's paid out and the bank
  account. With nothing left to collect, the window opens straight away for you to enter the
  amount.
- Switching **Insurance** on insures the shipment for what it carries (the order total when
  nothing has been shipped yet), or for the cash on delivery amount if that's higher; **Change**
  sets another value. With neither known, the window opens straight away.
- Both are only shown for carriers that offer them, unless the shipment already has them; then a
  note says the carrier doesn't, and **Change** is gone.

### Sender

The address the parcels go out from, with **Change** to pick another of your sender addresses or
**A new address** (see [Typing in a sender, an account or a parcel](#typing-in-a-sender-an-account-or-a-parcel)).
Without one chosen, the parcels go out from the store's address or its shipping origin, as **Fall
back to the store's address** and **Fall back to the shipping origin** allow (see the
[configuration](configuration.md#sender)). By default that's the shipping origin.

Next to it, how the parcels get to the carrier:

- **A courier collects them**: the collection dates and time windows the carrier offers for your
  sender address are read on their own, and the first free one is chosen; pick another from the
  lists if you like. If they can't be read, the reason shows, with **Try again** only when asking
  again may help.
- **I drop them off at a point**, for carriers that take parcels that way.
- **A courier collects them later, with other shipments**: a collective pickup, booked later from
  the shipments list (see [Managing shipments](managing-shipments.md)). It's offered when Polkurier
  says the carrier can collect the parcels that way, which it's asked whenever the carrier, the
  sender or the parcels change. On Magento's New Shipment
  page it's offered for every carrier, and Polkurier refuses the label if it can't.

### Recipient

The recipient's name, address, phone and e-mail, with **Change** to correct them. For carriers that
deliver to pickup points, the point the customer chose is shown with its address as it is today;
**Change** or **Choose on map** opens the map. A point that has closed, or no longer exists, since
the customer chose it is marked in red, and the order can't be placed until you choose another.
A point belongs to the carrier it was chosen for: choosing another carrier clears it, and going
back to that carrier brings it back.

### Before you place it

While you fill in the form, only softer warnings show, quietly, in the section they're about: cash
on delivery above what's left to collect, or a parcel lighter than what's in it, say. Each has a
**Fix** link where there's something to open. Polkurier's
current messages about the chosen carrier, such as delays or extra fees, are shown under the
carrier.

When you place the order with something missing, the order isn't sent. Each section with a problem
is marked in red, with what's missing under its title, and the page scrolls to the first. Examples
are a missing pickup point, collection date or parcel size. **Fix** opens what fixes it: the parcel
in question, the pickup point map, or the window with the field; fields such as the collection date
are marked themselves. If the carrier doesn't collect cash on delivery or insure shipments and
you've set either, **Fix** opens the carrier window to choose another carrier. The marks go away as
you fix things.

### Parcels

On the right, one line per parcel, with its size and weight and what it holds; the pencil and bin
icons edit and remove it. **Add a parcel** lets you start from one of your pack templates or enter
the size yourself (**A parcel without a template**); either way, the parcel's window opens before
the parcel is added, with a template's type, size and weight filled in. A template of another kind
(envelopes, pallets or parcels) replaces the parcels you have, once you confirm. In the parcel's
window, **What it holds** lists the items being shipped: enter how many of each go in this parcel.
Until you type a weight yourself, the parcel's weight follows **Parcel weight**: what they weigh,
or the template's weight. You're warned when a parcel is lighter than what's in it. A shipment's
parcels are all of one kind: parcels, envelopes or pallets, by their type, and the carrier has to
take that kind. Below the list, you see what no parcel holds yet. Saying what each parcel holds is
optional; when you do, Magento's shipment keeps it as its packages, so **Show Packages** on the
Magento shipment shows what went in which parcel. A note links to the pack templates page when you
have none yet, or none is marked as the default for new shipments.

### Placing the order

Below the parcels are the price and, if another carrier would be cheaper, a **Switch** link.

Click **Place the Polkurier order** at the top of the page. If a field isn't
filled in correctly, the window holding it opens so you can see what's wrong.

When Polkurier accepts the shipment, the Polkurier order's page opens, and:

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
another one for the same order until this is settled. A clear refusal, such as a parcel too big
for the carrier, or Polkurier not being reachable at all, isn't unclear: the shipment just fails,
with the reason, and you can correct it and send it again straight away, with **Send again**.

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
