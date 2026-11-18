<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Checkout

[Developer guide](README.md) › Checkout

## The carrier

[`Model\Carrier\Polkurier`](../../src/Model/Carrier/Polkurier.php) is a regular Magento carrier with
the code `polkurier`, extending `Magento\Shipping\Model\Carrier\AbstractCarrier`. Each Polkurier
carrier the merchant switches on becomes one method: `polkurier_DPD`, `polkurier_INPOST_PACZKOMAT`
and so on. The method code is the carrier code on the Polkurier account. Three static helpers work
with these codes:

- [`Polkurier::extractMethodCode()`](../../src/Model/Carrier/Polkurier.php) extracts the carrier code
  from a shipping method, or returns null for other carriers' methods;
- [`Polkurier::isOrderOf()`](../../src/Model/Carrier/Polkurier.php) tells whether an order was
  placed with a Polkurier method;
- [`Polkurier::buildShippingMethod()`](../../src/Model/Carrier/Polkurier.php) builds the method code from a
  carrier code.

Checkout offers the carriers that are switched on and that the account offers. Carriers for abroad
are offered only for destinations outside Poland, the others only within Poland. If the account's
carrier list can't be fetched, the carriers are treated as domestic and still offered. A carrier
the merchant's pricing leaves out isn't offered, even with free shipping; the others cost 0 when
the request has free shipping. To remove methods on your own conditions, see
[Extending the module](extending.md#offering-methods-conditionally).

Each method is titled with its label for the store
([`CarrierConfigInterface::getLabelForStore()`](../../src/Api/Data/CarrierConfigInterface.php)).
The carrier title is empty, so the storefront shows the method label alone. The carrier's logo is in
the `method_logo` extension attribute of `Magento\Quote\Api\Data\ShippingMethodInterface`, and the
method row template shows it.

`getTrackingInfo()` returns the carrier's tracking link for a waybill, titled with the method's
label in the order's store. The carrier also implements Magento's label hooks, described in
[Shipping labels in Magento](magento-labels.md).

## Pricing

Prices are the merchant's own. Each carrier has a pricing model, and each model has a calculator
([`Model\Carrier\Pricing\PriceCalculatorInterface`](../../src/Model/Carrier/Pricing/PriceCalculatorInterface.php)).
A calculator gets the carrier's settings, the cart's subtotal and its total weight in kilograms, and
returns a price or `null` for "not offered".

| Pricing model | Calculator |
|---|---|
| `FIXED` | [`Model\Carrier\Pricing\FixedPriceCalculator`](../../src/Model/Carrier/Pricing/FixedPriceCalculator.php) |
| `TIER_SUBTOTAL` | [`Model\Carrier\Pricing\TieredPriceCalculator`](../../src/Model/Carrier/Pricing/TieredPriceCalculator.php) (virtual type `Polkurier\Shipping\Model\Carrier\Pricing\SubtotalTiersVirtual`) |
| `TIER_WEIGHT` | [`Model\Carrier\Pricing\TieredPriceCalculator`](../../src/Model/Carrier/Pricing/TieredPriceCalculator.php) (virtual type `Polkurier\Shipping\Model\Carrier\Pricing\WeightTiersVirtual`) |
| `TIER_SUBTOTAL_WEIGHT` | [`Model\Carrier\Pricing\TieredPriceCalculator`](../../src/Model/Carrier/Pricing/TieredPriceCalculator.php) (virtual type `Polkurier\Shipping\Model\Carrier\Pricing\SubtotalWeightTiersVirtual`) |

The settings are stored as JSON in `price_info`, and their shape depends on the model. With tiers,
the highest tier the cart reaches wins; a cart that reaches none gets `null`.

The subtotal is the cart's value after discounts, in the base currency. It's gross or net as
`checkout/tier_cart_value` says (gross by default).

To replace a calculator or adjust every price, see [Extending the module](extending.md#pricing).

## Pickup points

What [`CarrierFeatureResolver`](../../src/Model/Carrier/CarrierFeatureResolver.php) reports about a
carrier decides whether it needs a pickup point. For those methods, the checkout shows a point
picker in the method row, which opens the Polkurier map; opening the map selects the method. To
replace or restyle the picker, see [Extending the module](extending.md#checkout-front-end). The
map's configuration reaches the page under `window.checkoutConfig.pickupPoints`
([`Model\PickupPoint\MapConfigProvider`](../../src/Model/PickupPoint/MapConfigProvider.php)).

The map needs a token from Polkurier. A page left open fetches a fresh one before it runs out. When
Polkurier gives no token at all, the storefront doesn't offer the methods that require a point,
because the shopper couldn't choose one. Orders created in the admin keep them, since the merchant
sets the point when shipping.

The chosen point goes from checkout to the shipment like this:

```
shipping step: a point chosen on the map
        │                      sent as pickup_point_id, an extension attribute of
        │                      ShippingInformationInterface; no point, no next step
        │
PointSelectionRecorder::select()
├── not a Polkurier method ..................................... stored point removed
├── no point, or a method that takes none
│   ├── the method needs a point ............................... refused
│   └── otherwise .............................................. stored point removed
└── point looked up with Polkurier
    ├── unknown, closed, or lacking a function the map
    │   requires (Show only points that) ....................... refused
    ├── found .................................................. stored with its address and
    │                                                            whether it takes cash on delivery
    └── Polkurier can't be asked ............................... stored as typed
        │
polkurier_quote_point                          the point stored for the cart
        │
sales_model_service_quote_submit_before
├── the method needs a point and none is stored for it ......... refused; not for admin orders
└── the point is added to the order's shipping description
        │
sales_model_service_quote_submit_success
└── the point is copied to the order ........................... a failure is only logged
        │
polkurier_order_point
        │
new shipment                                   the default draft's recipient point
├── shipment screen, New Shipment page ......................... looked up again; closed or
│                                                                gone: another point is needed
└── Polkurier order placed with another carrier or point ....... the order's point follows it
```

The shipping description reads, for example, "Paczkomat InPost, pickup point WAW01A (address)"; the
address is left out when it would overflow the 255-character column. Magento prints that
description in e-mails, the customer's account, invoices, packing slips and PDFs. How the order's
point follows its Polkurier orders, and how the customer's choice is kept, is in
[Shipments](shipments.md#the-orders-shipping).

On the order, the point is the `polkurier_point` extension attribute of
`Magento\Sales\Api\Data\OrderInterface`.

[`Api\QuotePointRepositoryInterface`](../../src/Api/QuotePointRepositoryInterface.php) and
[`Api\OrderPointRepositoryInterface`](../../src/Api/OrderPointRepositoryInterface.php) are the only
code that reads and writes the stored points. They return
[`Api\Data\QuotePointInterface`](../../src/Api/Data/QuotePointInterface.php) and
[`Api\Data\OrderPointInterface`](../../src/Api/Data/OrderPointInterface.php).
[`OrderPointRepositoryInterface::copyFromQuote()`](../../src/Api/OrderPointRepositoryInterface.php)
copies the point chosen at checkout to an order. To record a point with the checks above, call
[`Model\PickupPoint\PointSelectionRecorder::select()`](../../src/Model/PickupPoint/PointSelectionRecorder.php).

## Cash on delivery at checkout

The payment methods configured as cash on delivery are turned off when, for any of the cart's
addresses, the chosen carrier can't collect cash on delivery or the chosen point doesn't accept it
([`Observer\Checkout\RestrictCashOnDeliveryObserver`](../../src/Observer/Checkout/RestrictCashOnDeliveryObserver.php),
on `payment_method_is_active`). An order with such a payment method is then refused, as with any
payment method that isn't available.

When the carrier collects cash on delivery but the chosen point doesn't accept it, the payment step
shows a note in its `afterMethods` region.

`point_cod` is null when it isn't known whether the point accepts cash on delivery, for example
when the point couldn't be checked with Polkurier. A null counts as accepting.

## Multi-address checkout

Magento's multi-address checkout is supported by a separate bridge module,
`polkurier/magento2-multishipping` (`Polkurier_Multishipping`), which requires this module and
`magento/module-multishipping`. Its developer guide describes it.

The bridge uses this module's pickup points as they are. Points are stored per quote address
(`polkurier_quote_point.quote_address_id`, 0 in the regular checkout), with the same checks, map
and shipping description as the regular checkout. Cash on delivery is restricted for every address
of a multi-address cart.

Next: [Shipments](shipments.md)
