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
for other carriers' methods.

`collectRates()`:

1. returns nothing when the method is disabled for the website;
2. loads the website's switched-on carriers (`Model\Carrier\CarrierConfigs`), leaving out those
   the account no longer offers;
3. checks them against the account's carrier list (`Model\Carrier\CarrierCatalog`, cached). For a
   destination outside Poland, only carriers that deliver abroad are kept. If the list can't be
   fetched, domestic carriers are still offered;
4. prices each carrier through `Model\Pricing\PriceCalculatorPool`, or at 0 when the request has
   free shipping. A carrier the calculator returns `null` for is left out;
5. titles each method with `CarrierConfigInterface::getLabelForStore()`.

The carrier title is deliberately empty. Magento shows "carrier title - method title" in many
places, and the storefront must never show the Polkurier name. The checkout mixin
`view/frontend/web/js/view/method-title-mixin.js` handles the places where Magento would still
print a separator. In the multi-address checkout, `Plugin\Multishipping\ShippingStepPlugin` and
`Plugin\Multishipping\OverviewPlugin` give Polkurier methods a neutral heading instead.

Each method can carry the carrier's logo. `Plugin\Quote\ShippingMethodLogoPlugin`
(`afterModelToDataObject` on Magento's `ShippingMethodConverter`) sets it as the `method_logo`
extension attribute of `ShippingMethodInterface`, named neutrally because it reaches the
storefront, and the method row template shows it. The multi-address checkout prints method titles
as escaped text, so there `Block\Multishipping\MethodLogos` hands a code-to-URL map to
`view/frontend/web/js/multishipping/method-logos.js`, which puts each logo in front of its label.

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
rendered in a `pickup-point-choice` region of the module's copy of the method row template) that
opens the Polkurier map through `view/frontend/web/js/model/points-map.js`. The map script is
loaded from Polkurier's map host and needs a token, which `Model\Checkout\MapTokenProvider`
fetches and caches per account. The map configuration reaches the page through
`Model\Checkout\MapConfigProvider`, under `window.checkoutConfig.pickupPoints`.

From there:

1. The payload extender mixin adds `pickup_point_id` to the shipping information's extension
   attributes. The shipping step's validation mixin refuses to continue without a point.
2. `Plugin\Checkout\ShippingInformationManagementPlugin` passes it to
   `Model\Checkout\PointSelection`. That class checks the point with Polkurier and stores it in
   `polkurier_quote_point`, or removes it when the customer switches to another method.
3. `Observer\ValidatePickupPoint` (`sales_model_service_quote_submit_before`) checks again when
   the order is placed. It's disabled in the admin area, where orders are created without a
   picker; the merchant sets the point when shipping instead.
4. `Observer\CopyPickupPointToOrder` (`sales_model_service_quote_submit_success`) copies it to
   `polkurier_order_point`.
5. `Plugin\Sales\OrderRepositoryPlugin` exposes it as the `polkurier_point` extension attribute of
   `OrderInterface`.

`Model\OrderPointStore` is the one place that reads and writes both tables.

## Cash on delivery at checkout

`Observer\RestrictCashOnDelivery` (`payment_method_is_active`) hides the payment methods
configured as cash on delivery when the chosen carrier can't collect, or the chosen point doesn't
accept it, for any of the cart's addresses.

## Multi-address checkout

Points are stored per quote address (`polkurier_quote_point.quote_address_id`, 0 in the regular
checkout). The multi-address checkout is block-based, so it has its own pieces:

- `Plugin\Multishipping\ShippingStepPlugin` renders `Block\Multishipping\PickupPoint` under each
  address, through the step's `getItemsBoxTextAfter()` slot. The widget
  (`view/frontend/web/js/multishipping/pickup-point.js`) posts the point as
  `pickup_point[<address id>]`.
- `Plugin\Multishipping\CheckoutPlugin` stores and checks the points in `setShippingMethods()` and
  requires them again in `createOrders()`.
- `Observer\TagMultishippingOrder` notes each order's quote address, which Magento doesn't keep,
  and `Observer\CopyPickupPointsToOrders` (`checkout_submit_all_after`) copies the points once the
  orders are saved.

## Styling

`view/frontend/web/css/pickup-point.css` styles the picker in both checkouts. It's loaded through
the checkout layouts, so it doesn't depend on the theme's LESS pipeline. The picker uses the
theme's own `action primary` button classes.

Next: [Shipments](shipments.md)
