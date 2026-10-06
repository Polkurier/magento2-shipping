<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Configuration

[User guide](README.md) › Configuration

All settings are in **Stores > Configuration > Sales > Delivery Methods > Polkurier**. The
**Polkurier > Settings > Configuration** menu item takes you to the same page.

The module works with one Polkurier account for the whole Magento installation, so the account
settings (**Polkurier account**, **Synchronization**, **Logging**) are set at the
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
  30 seconds is fine for almost everyone. Your customers' checkout waits at most 5 seconds,
  whatever this says: it goes on without the check rather than keep them waiting.
- **Identify orders to Polkurier by**: each Polkurier order carries the Magento order's number
  (**Order ID**, the default) or the shipment's (**Shipment ID**), with this Magento installation's
  id (see **Installation ID** below), e.g. `magento2-3f9a1c7e5b20`. If Polkurier's answer to an
  order is lost, the module finds the order by them; an order shipped in parts has one number for
  all its shipments, and the module tells them apart by their parcels and recipient. With
  **Shipment ID**, a new shipment's number is taken before the order is placed, so the Magento
  shipment gets the number Polkurier has; a refused order leaves a gap in the shipment numbers.
  The order number is always sent as well, for you to see in the Polkurier panel.
- **Installation ID**: leave it empty, and the id made from this installation's encryption key,
  shown greyed in the field, is used. Enter another on a copy of the shop that shares that key and the Polkurier account, such
  as a staging copy, so that its orders aren't taken for the shop's: up to 50 letters, digits,
  dots, dashes and underscores, e.g. `shop-staging`. A copied database brings this setting along,
  so on the copy set it in `app/etc/env.php` instead:

  ```bash
  bin/magento config:set --lock-env carriers/polkurier/general/application_id shop-staging
  ```

  Orders already placed keep the id they were placed with.

- **Available carriers**: the carriers you actually work with. Only these appear under
  **Polkurier > Settings > Carriers**, at checkout and in the carrier lists when shipping, so the
  ones you don't use don't clutter anything. Carriers you
  leave out keep their settings and come back as they were if you select them again. With
  nothing selected, all of the account's carriers are available.

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

The group also holds one setting of the module's own:

- **Cart value for price tiers**: what carriers priced by cart subtotal compare their tiers with:
  the cart's value after discounts **With tax** (the default), as the customer sees it, or
  **Without tax**. Can be set per website.

## Shipments

These are the defaults every new shipment starts from, on the shipment screen and on Magento's
shipment page alike. You can change all of them for each shipment. They come in five groups.

### New shipments

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
  takes a bigger template to stay under it, which may take more parcels; an item too heavy for
  every template is still packed, and its parcel pointed out.

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

- **Synchronize with Polkurier on schedule**: keeps the data under **Polkurier > Settings** in
  line with your Polkurier account. Addresses, bank accounts and pack templates are synchronized
  every hour, and the carriers your account offers once a day, since they rarely change. Changes
  you make in Magento are sent straight away regardless; the scheduled runs pick up changes made
  in the Polkurier panel.
- **Update shipment statuses on schedule**: follows each shipment until it's delivered,
  returned or cancelled.
- **Update shipment statuses**: how often: every 10, 15, 20 or 30 minutes (the default), or
  every hour. More often keeps the statuses fresher, at the cost of more requests to Polkurier.
- **Status webhook secret**: lets Polkurier tell the shop as soon as a shipment changes, instead
  of waiting for the next scheduled update. In the Polkurier panel, under **Ustawienia >
  Aplikacje zewnętrzne**, add an application of the WebHooks type with the URL shown under the
  field, e.g. `https://shop.example.com/polkurier/webhook/shipment_update`; the panel then shows its
  secret, which you paste here. The URL must be https, and reachable from the internet. Leave the
  field empty to keep the webhook off.

  Polkurier sends each change once and doesn't try again, so keep the scheduled updates on: they
  catch whatever a webhook missed, and can run less often. Polkurier sends every webhook of the
  account every order of the account, so a copy of the shop, such as a staging copy, should use
  a Polkurier account of its own.

The scheduled jobs need Magento's cron. All of these are set for the whole installation (default
scope).

## Logging

Requests to Polkurier are logged in `var/log/polkurier`, a pair of files a month: requests and
answers in `YYYY-MM-access.log` (e.g. `2026-10-access.log`), failed requests, warnings and errors
in `YYYY-MM-error.log`. The module's own warnings go there whatever these settings say. Old
months' files stay until you delete them.

- **Log failed requests**: writes requests that failed, with Polkurier's answer.
- **Log all traffic of**: also logs successful requests of the kinds you select, which helps if
  Polkurier support asks for details. By default, every kind that changes something on your
  Polkurier account: placing and cancelling orders, booking pickups, payouts, saving or deleting
  addresses, bank accounts and pack templates, account settings, confirmation codes and support
  tickets. Requests that only read are left out.
- **Log personal data**: off by default, so names, addresses, email addresses, phone numbers, tax
  numbers and bank account numbers are replaced with `***`. Turn it on only while you need them,
  e.g. to show support exactly what was sent.
- **Save files**: off by default, so a label, protocol, invoice or attachment in a logged request
  or answer is noted only by its size. On, each one is saved as a file, and the log says where.
  A label shows the recipient's name and address even with **Log personal data** off.
- **Save files in**: the directory for those files, relative to Magento's root or absolute. Empty
  means `var/log/polkurier/files`. Each month's files go in a directory of their own in it, e.g.
  `2026/10`. It can't be in the `pub` directory, which the web server serves.

Saved files and old months' logs aren't deleted by the module: delete them when you no longer need
them, as they may hold your customers' names and addresses.

The other settings are shown only when **Log failed requests** is on. Your API token, security PINs
and confirmation codes are always hidden, so with personal data and files off the log is safe to
send to Polkurier support.

Next: [Carriers and prices](carriers-and-prices.md)
