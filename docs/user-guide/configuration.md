<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Configuration

[User guide](README.md) › Configuration

All settings are in **Stores > Configuration > Sales > Delivery Methods > Polkurier**. The
**Polkurier > Settings > Configuration** menu item takes you to the same page.

The module works with one Polkurier account for the whole Magento installation, so the account
settings (**Polkurier account**, **Available carriers**, **Synchronization**, **Logging**) are set at the
default scope only. Most other settings can be set for a particular website with the scope
switcher at the top of the page, and a few texts also per store view; they're marked below.

## Connecting your account

Under **Polkurier account**:

- **Customer ID** and **API token**: your credentials from the Polkurier panel. Both are stored
  encrypted and are never shown again once saved.
- **Sandbox**: sends everything to Polkurier's test environment instead of the real one. Shipments
  placed there aren't real, so it's a safe way to try the whole process before you go live. A
  sandbox account has its own customer ID and token; ask api@polkurier.pl for one. It's on by
  default: switch it off when you enter your production credentials.
- **Test connection** checks the customer ID and token you've typed, before you save them, and
  tells you whether Polkurier accepted them.
- **Request timeout (seconds)**: how long to wait for Polkurier before giving up. The default of
  30 seconds is fine for almost everyone.
- **Identify orders to Polkurier by**: each Polkurier order carries the Magento shipment's number
  (the default) or the order's, with an id of this Magento installation shown under the setting,
  e.g. `magento2-3f9a1c7e5b20`. If Polkurier's answer to an order is lost, the module finds the
  order by them. A new shipment's number is taken before the order is placed, so the Magento
  shipment gets the number Polkurier has; a refused order leaves a gap in the shipment numbers.
  The order number is always sent as well, for you to see in the Polkurier panel.

When the credentials change, the module forgets everything it cached for the old account, such as
the carrier list.

## The delivery method

The **Shipping method in checkout** group at the top holds Magento's usual settings for a shipping
method:

- **Enabled**: switches the Polkurier methods on at checkout for the website. You can still ship
  orders with Polkurier from the admin while this is off, which is useful if you only want to use
  Polkurier for shipping and not offer its carriers at checkout.
- **Title**: only used in the admin, to tell the Polkurier methods apart from other shipping
  methods. Customers see each carrier's own label instead (see
  [Carriers and prices](carriers-and-prices.md)). Can be set per store view.
- **Ship to Applicable Countries** and **Ship to Specific Countries**: limit the countries the
  methods are offered for. Carriers that only deliver in Poland are never offered for other
  countries anyway.
- **Show Method if Not Applicable** and **Displayed Error Message**: whether customers see a
  message instead of the methods when they can't be used.
- **Sort Order**: the position of the Polkurier methods among other shipping methods. Each
  carrier also has its place among the Polkurier methods, set by dragging it in the carriers list
  (see [Carriers and prices](carriers-and-prices.md)).

## Shipments

These are the defaults every new shipment starts from, on the shipment screen and on Magento's
shipment page alike. You can change all of them for each shipment. They come in five groups.

### New shipments

- **Available carriers**: the carriers you actually work with. Only these appear under
  **Polkurier > Settings > Carriers**, at checkout and in the carrier lists when shipping, so the
  ones you don't use don't clutter anything. Carriers you
  leave out keep their settings and come back as they were if you select them again. With
  nothing selected, all of the account's carriers are available.
- **Parcels go as**: parcels (boxes, the default), envelopes or pallets. A new shipment's parcels
  are of this kind, and automatic packing uses only pack templates of it, so a shipment never mixes
  kinds. A carrier that doesn't take the kind gets the first kind it takes. Can be set per website.
- **Additional services selected by default**, such as SMS notifications. Only services the chosen
  carrier offers get ticked.
- **Another sender on the label by default**: one of your addresses marked **Can be a cover
  address**, printed on the label as the sender instead of the real one, or none. Only carriers
  that offer it get it.
- **Insure for the order total by default**. A shipment of part of the order is insured for what
  its items are worth. Some carriers insist on insurance with cash on delivery; for those,
  shipments with cash on delivery are always insured.
- **Shipment description**: what goes into the shipment's description field. `%s` is replaced by
  the order number, and Polkurier keeps up to 30 characters. The default is the order number
  alone, which also makes the shipment easy to find in the Polkurier panel. Can be set per store
  view.

### Sender

- **Fall back to the store's address**: when you haven't marked a default sender address under
  **Polkurier > Settings > Addresses**, ship from the address in **General > Store Information**
  instead.
- **Fall back to the shipping origin**: the same, with the address in **Sales > Shipping
  Settings > Origin**. With both switched on, the store's address is tried first, and the shipping
  origin is used when the store's address is incomplete.
- **Sender e-mail with the store's addresses**: which of the store's e-mail addresses goes with
  either of those.

### Parcels

- **Parcel weight**: the weight a parcel of a pack template is priced and sent with.
  - **What the products in it weigh** (the default): the sum of the products' weights, and the
    template's **Tare**, if it has one.
  - **The pack template's weight**: the template's **Maximum weight**, whatever is in the parcel.
    Choose this when your products' weights aren't filled in, or aren't reliable.

  Either way, a parcel holding a product without a weight takes its template's weight, since it
  can't be weighed, and a parcel without a template weighs what's in it. You can still change the
  weight of any parcel before placing the order.
- **Pack new shipments automatically**: whether a new shipment starts with its items packed into
  your pack templates, on the Polkurier shipment screen and in Magento's packaging window. Yes by
  default. With No, it starts with one parcel of your default pack template, or none, and
  **Pack automatically** still packs the items when you want it to.
- **Packing margin (cm)**: added to every dimension of every product when items are packed into
  parcels automatically, for products a little bigger than their shipping dimensions say. 0 by
  default.
- **Pack template weight limits**: what packing automatically does with the pack templates'
  **Maximum weight**. **Only warn about heavier parcels** packs by size and points out a
  parcel over its template's limit; **Keep parcels under them where possible** splits parcels or
  takes a bigger template to stay under it, which may take more parcels.

### Cash on delivery

- **Cash on delivery payment methods**: the payment methods that mean the customer pays the
  courier. Orders paid with one of them get cash on delivery for the order total, or for what's
  left of it when the order ships in parts. At checkout these
  payment methods are hidden when the chosen carrier or pickup point can't collect cash.
- **Cash on delivery payout time**: how quickly Polkurier pays out the money after delivery:
  standard, next day, or within 4 days. Faster payouts may cost more; check your Polkurier
  contract.
- **Cash on delivery paid out to**: your bank account, your Polkurier money box, or a postal order.

### Labels and Magento shipments

- **Label format**: PDF, or a printer language for thermal label printers (ZPL at 203 or 300 dpi,
  EPL, Datamax). Applies to labels you print from **Polkurier > Sales > Shipments** and the
  shipment details page. Labels stored with Magento shipments are always PDF.
- **Shipping labels on Magento's shipment page**: lets **Create Shipping Label** on Magento's New
  Shipment page place the Polkurier order, for orders placed with a Polkurier method.
- **Send the shipment e-mail to the customer**: whether Magento's usual "your order has shipped"
  e-mail goes out when the module ships an order. On Magento's shipment page, the page's own
  "Email Copy of Shipment" checkbox decides instead.
- **Polkurier shipment screen**: adds a **Ship with Polkurier** button to the page of an order
  placed with a Polkurier method.

Both ways of shipping are on by default and can be used side by side. See
[Shipping an order](shipping-an-order.md) for how they differ.

## Pickup point map

The map customers use to pick a parcel locker or pickup point:

- **Initial latitude**, **Initial longitude** and **Initial zoom**: where the map opens when the
  shipping address doesn't give it a better starting point. The default is central Warsaw.
- **Show the list of points**: a list next to the map.
- **Show the search field**: lets customers search for an address or a point.
- **Show only points that**: limits the map to points that can do certain things: hand parcels
  out, accept parcels for sending, or take cash on delivery. Leave everything unselected to show
  all points.

The first five can be set per store view.

## Synchronization

- **Synchronize carriers, addresses, bank accounts and pack templates hourly**: keeps the data
  under **Polkurier > Settings** in line with your Polkurier account, including which carriers it
  offers. Changes you make in Magento are sent straight away regardless; the hourly run picks up
  changes made in the Polkurier panel.
- **Update shipment statuses on schedule**: follows each shipment until it's delivered,
  returned or cancelled.
- **Update shipment statuses**: how often: every 10, 15, 20 or 30 minutes (the default), or
  every hour. More often keeps the statuses fresher, at the cost of more requests to Polkurier.

Both need Magento's cron, and both are set for the whole installation (default scope).

## Logging

Requests to Polkurier are logged to `var/log/polkurier.log`. The module's own warnings go there
whatever these settings say.

- **Log failed requests**: writes requests that failed, with Polkurier's answer.
- **Log all traffic of**: also logs successful requests of the kinds you select, which helps if
  Polkurier support asks for details.
- **Log personal data**: off by default, so names, addresses, email addresses, phone numbers, tax
  numbers and bank account numbers are replaced with `***`. Turn it on only while you need them,
  e.g. to show support exactly what was sent.
- **Save files**: off by default, so a label, protocol, invoice or attachment in a logged request
  or answer is noted only by its size. On, each one is saved as a file, and the log says where.
  A label shows the recipient's name and address even with **Log personal data** off.
- **Save files in**: the directory for those files, relative to Magento's root or absolute. Empty
  means `var/log/polkurier-files`. It can't be in the `pub` directory, which the web server
  serves.
- **Keep saved files for (days)**: files older than this are deleted every night, 7 days by
  default, also after you turn **Save files** off. 0 keeps them until you delete them. Only the
  files the module saved are deleted, so other files in the directory stay. Needs Magento's cron.

The other settings are shown only when **Log failed requests** is on. Your API token, security PINs
and confirmation codes are always hidden, so with personal data and files off the log is safe to
send to Polkurier support.

Next: [Carriers and prices](carriers-and-prices.md)
