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
appears in the method's row, in the last column, where Magento names the carrier. It opens a map
centred on the shipping postcode, where the customer can browse, search and pick a point, and
selects the method. The chosen point is shown above the button, which lets them pick another. On
a narrow phone screen, the button's label wraps within the column.

The customer can't continue to payment without a point. The point is checked when they move on to
payment and once more when the order is placed, so a point that has closed or stopped taking
parcels in the meantime is caught before the order goes through.

What the map shows can be adjusted on the configuration page, under
[Pickup point map](configuration.md#pickup-point-map).

## Cash on delivery

If the customer picks a carrier or a point that can't collect cash on delivery, your cash on
delivery payment methods (see **Cash on delivery payment methods** in the
[configuration](configuration.md#cash-on-delivery)) are hidden for that order.

## Multi-address checkout

Customers who ship to several addresses at once (**Check Out with Multiple Addresses** in the
cart) get the same choice for each address. When a method that delivers to a point is selected
for an address, a **Choose a pickup point** button appears under it, and the customer can't
continue until every such address has its point. Each of the resulting orders carries its own
point. Carrier logos are shown there as well.

This needs the multi-address checkout module, `polkurier/magento2-multishipping` (see
[Installation](installation.md#multi-address-checkout)).

## After the order is placed

The chosen point is saved with the order and named with its shipping method, for example "Paczkomat
InPost, pickup point WAW01A (ul. Prosta 1, 00-001 Warszawa)". Customers see it wherever the method
is shown: on the payment step, in the order, invoice and shipment e-mails, in their account and on
printed documents; in the multi-address checkout, on the review page too. In the admin it's in
**Shipping & Handling Information** on the order page, read-only. It's filled in automatically when
you ship the order, whichever way you do it.

If the customer asks for another point, choose it when you ship the order: the point can be changed
for each shipment, on the shipment screen or in the shipping label options. The order page only
shows the point the customer chose.

Next: [Shipping an order](shipping-an-order.md)
