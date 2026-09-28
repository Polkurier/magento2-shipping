<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Checkout

[User guide](README.md) › Checkout

## Shipping methods

At the shipping step, customers see every carrier you switched on that their cart qualifies for
(see [Carriers and prices](carriers-and-prices.md)), each with your label and your price, and
with its logo in front of the name if you uploaded one. The
Polkurier name appears nowhere: not in the method list, the order summary, e-mails, the
customer's account, or the page source.

If Polkurier can't be reached while the methods are loaded, customers still see your domestic
carriers; nothing waits on Polkurier beyond a list the module keeps cached.

## Pickup points

For carriers that deliver to parcel lockers or pickup shops, a **Choose a pickup point** button
appears under the method. It opens a map centred on the shipping address, where the customer can
browse, search and pick a point. The chosen point is shown under the method, and the button
changes to let them pick another.

The customer can't continue to payment without a point. The point is checked when they move on to
payment and once more when the order is placed, so a point that has closed or stopped taking
parcels in the meantime is caught before the order goes through.

What the map shows can be adjusted on the configuration page, under
[Pickup point map](configuration.md#pickup-point-map).

## Cash on delivery

If the customer picks a carrier or a point that can't collect cash on delivery, your cash on
delivery payment methods (see **Cash on delivery payment methods** in the
[configuration](configuration.md#shipments)) are hidden for that order.

## Multi-address checkout

Customers who ship to several addresses at once (**Check Out with Multiple Addresses** in the
cart) get the same choice for each address. When a method that delivers to a point is selected
for an address, a **Choose a pickup point** button appears under it, and the customer can't
continue until every such address has its point. Each of the resulting orders carries its own
point. Carrier logos are shown there as well.

## After the order is placed

The chosen point is saved with the order. In the admin you'll find it on the order page, in the
**Polkurier** box on the Information tab, with whether it accepts cash on delivery. It's filled in
automatically when you ship the order, whichever way you do it.

If the customer asks for another point, type its code into **Pickup point** in that box and click
**Save pickup point**, or click **Choose on map** and pick the point there; a point chosen on the
map is saved straight away. The code is checked with Polkurier for the order's carrier, and the point's
address and cash on delivery flag are updated. For carriers that don't need a point, clearing the
field removes it. Each change is noted in the order's comments. Shipments already placed keep the
point they were placed with. Changing the point needs the **Create shipments** permission.

Next: [Shipping an order](shipping-an-order.md)
