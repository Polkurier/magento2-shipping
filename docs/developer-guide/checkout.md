<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Checkout

[Developer guide](README.md) › Checkout

## The carrier

`Model\Carrier\Polkurier` is a regular Magento carrier with the code `polkurier`, extending
`AbstractCarrier`. Each Polkurier carrier the merchant switches on becomes one method:
`polkurier_DPD`, `polkurier_INPOST_PACZKOMAT` and so on. The method code is the carrier code on the
Polkurier account. `Polkurier::methodCodeOf()` extracts it from a shipping method, or returns null
for other carriers' methods; `Polkurier::isOrderOf()` tells whether an order was placed with one. The carrier class is a thin adapter: rates come from
`Model\Carrier\RateCollector`, tracking titles from `Model\Shipment\TrackingLookup`, and shipping
labels from `Model\Shipment\NativeLabel`.

`collectRates()` returns nothing when the method is disabled for the website; otherwise
`RateCollector::collect()`:

1. loads the switched-on carriers (`Model\Carrier\CarrierConfigs`), leaving out those the account
   no longer offers;
2. checks them against the account's carrier list (`Model\Carrier\CarrierCatalog`, cached). For a
   destination outside Poland, only carriers that deliver abroad are kept. If the list can't be
   fetched, domestic carriers are still offered;
3. prices each carrier through `Model\Pricing\PriceCalculatorPool`. A carrier the calculator
   returns `null` for is left out, even with free shipping; the others cost 0 when the request has
   free shipping;
4. titles each method with `CarrierConfigInterface::getLabelForStore()`.

The carrier title is deliberately empty. Magento shows "carrier title - method title" in many
places, and the storefront must never show the Polkurier name. The checkout mixin
`view/frontend/web/js/view/method-title-mixin.js` handles the places where Magento would still
print a separator. In the multi-address checkout, the bridge module's plugins give Polkurier
methods a neutral heading instead (see [Multi-address checkout](#multi-address-checkout)).

Each method can carry the carrier's logo. `Plugin\Quote\ShippingMethodLogoPlugin`
(`afterModelToDataObject` on Magento's `ShippingMethodConverter`) sets it as the `method_logo`
extension attribute of `ShippingMethodInterface`, named neutrally because it reaches the
storefront, and the method row template shows it. The multi-address checkout prints method titles
as escaped text, so the bridge module puts the logos in with a script there.

Tracking is available: `getTrackingInfo()` finds the shipment by waybill
(`Model\Shipment\TrackingLookup`) and returns the carrier's tracking link, titled with the method's
label. The carrier also implements Magento's label hooks, described in
[Shipping labels in Magento](magento-labels.md).

## Pricing

Prices are the merchant's own. `Model\Pricing\PriceCalculatorInterface::calculate()` gets the
carrier's settings and a `RateContext` (subtotal after discounts and without tax, total weight),
and returns a price or `null` for "not offered". The pool picks a calculator by the carrier's
pricing model:

| Pricing model | Calculator |
|---|---|
| `FIXED` | `Model\Pricing\Fixed` |
| `TIER_SUBTOTAL` | `Model\Pricing\Tiers` (virtual type `SubtotalTiers`) |
| `TIER_WEIGHT` | `Model\Pricing\Tiers` (virtual type `WeightTiers`) |
| `TIER_SUBTOTAL_WEIGHT` | `Model\Pricing\Tiers` (virtual type `SubtotalWeightTiers`) |

The settings are stored as JSON in `price_info`, and their shape depends on the model. With tiers,
the highest tier the cart reaches wins; a cart that reaches none gets `null`.

## Pickup points

What `CarrierFeatures` reports about a carrier decides whether it needs a pickup point. For those
methods, the checkout shows a point picker (`view/frontend/web/js/view/checkout/pickup-point.js`,
rendered in a `pickup-point-choice` region of the module's copy of the method row template, in
the `col-carrier` cell, which the module's methods leave without a carrier title; the
`label_carrier_*` id that names the radio button is on the title's own span, so the point doesn't
become part of the method's name; opening the map selects the method) that opens the Polkurier map through
`view/frontend/web/js/model/points-map.js`. The map script is loaded from Polkurier's map host and
needs a token, which `Model\Checkout\MapTokenProvider` fetches and caches per account. The map
configuration reaches the page through `Model\Checkout\MapConfigProvider`, under
`window.checkoutConfig.pickupPoints`.

The token runs out some hours after it's issued: the token says when (it's a JWT with `exp`), and
`MapTokenProvider::getExpiry()` reads it, or takes four hours from when the token was fetched. The
page gets the token with `tokenExpires` and `tokenUrl`; opening the map asks `tokenUrl`
(`Controller\Map\Token`, the neutral frontName `pickup-points`, never cached) for a fresh one when
the page has none or it runs out within ten minutes, so a checkout left open still shows the map.
The admin's map does the same through `polkurier/shipment/mapToken`.

The methods that deliver to points reach the page whether or not there's a token right then. When
Polkurier gives no token at all, `RateCollector` leaves the methods that require a point out of the
rates: the shopper couldn't give the point the order would need. Orders created in the admin keep
them, since the merchant sets the point when shipping.

From there:

1. The payload extender mixin adds `pickup_point_id` to the shipping information's extension
   attributes. The shipping step's validation mixin refuses to continue without a point.
2. `Plugin\Checkout\ShippingInformationManagementPlugin` passes it to
   `Model\Checkout\PointSelection`. That class looks the point up with Polkurier, refuses it when
   it's closed or lacks a function the map is set to require (*Show only points that*), and
   stores it in `polkurier_quote_point`, or removes it when the customer switches to another
   method.
3. `Observer\ValidatePickupPointObserver` (`sales_model_service_quote_submit_before`) checks again when
   the order is placed. It's disabled in the admin area, where orders are created without a
   picker; the merchant sets the point when shipping instead.
4. `Observer\DescribePickupPointObserver`, on the same event (and, registered by the bridge module, on
   `checkout_type_multishipping_create_orders_single` for each multi-address order), adds the point
   to the order's shipping description before the order is saved, through
   `Model\Checkout\PointDescription`: "Paczkomat InPost, pickup point WAW01A (address)", the address
   left out when it would overflow the 255-character column. Magento prints that description in
   the order's e-mails, the customer's account, invoices, packing slips and PDFs, so the point
   shows wherever the method does. Tracks are titled with the carrier's label instead.
5. `Observer\CopyPickupPointToOrderObserver` (`sales_model_service_quote_submit_success`) copies it to
   `polkurier_order_point`. The order is placed by then, so a failure is only logged.
6. `Plugin\Sales\OrderRepositoryPlugin` exposes it as the `polkurier_point` extension attribute of
   `OrderInterface`.

`Model\OrderPointStore` is the one place that reads and writes both tables, handing out
`Api\Data\QuotePointInterface` and `Api\Data\OrderPointInterface`.

## Cash on delivery at checkout

`Observer\RestrictCashOnDeliveryObserver` (`payment_method_is_active`) hides the payment methods
configured as cash on delivery when the chosen carrier can't collect, or the chosen point doesn't
accept it, for any of the cart's addresses.

## Multi-address checkout

Magento's multi-address checkout (`Magento_Multishipping`) is often disabled or removed, so its
part is a bridge module of its own: `polkurier/magento2-multishipping` (`Polkurier_Multishipping`),
which requires this module and `magento/module-multishipping`. Its README describes its pieces.

What it builds on stays here: points are stored per quote address
(`polkurier_quote_point.quote_address_id`, 0 in the regular checkout), through
`Model\Checkout\PointSelection`; the picker's configuration is
`MapConfigProvider::getMapConfig()`, the same as the regular checkout's; the map is
`view/frontend/web/js/model/points-map.js`, the styles `view/frontend/web/css/pickup-point.css`;
and `Observer\DescribePickupPointObserver` names each order's point in its shipping description.
`Observer\RestrictCashOnDeliveryObserver` checks every address of a multi-address cart either way.

## Styling

`view/frontend/web/css/pickup-point.css` styles the picker in both checkouts. It's loaded through
the checkout layouts, so it doesn't depend on the theme's LESS pipeline. The picker uses the
theme's own `action primary` button classes.

Next: [Shipments](shipments.md)
