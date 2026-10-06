<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Extending the module

[Developer guide](README.md) › Extending the module

Prefer plugins on the service contracts and on the public methods listed here. Everything else
counts as internal and may change between versions.

## Changing the default shipment

Add an `afterCreateFromOrder` plugin on `Model\Shipment\DefaultDraftBuilder` to change what a new
shipment starts with: a different sender per store, extra services for certain products, parcels
computed from the cart. The shipment screen, Magento's label flow (for the options, not the
parcels, which come from the packaging popup) and the REST draft all pick it up.

```php
public function afterCreateFromOrder(
    DefaultDraftBuilder $subject,
    ShipmentDraftInterface $draft,
    \Magento\Sales\Model\Order $order,
): ShipmentDraftInterface {
    if ($this->hasFragileItems($order)) {
        $draft->setServices(\array_values(\array_unique([...$draft->getServices(), 'CHECK_CONTENT'])));
    }

    return $draft;
}
```

Service codes are the values of the SDK's `AdditionalServiceEnum`.

## Changing what's sent

An `afterMap` plugin on `Model\Mapper\CreateOrderMessageMapper` sees the final
`CreateOrderMessage` just before it goes out, whichever way the shipment was placed. The SDK's
messages are immutable, so build a new one with the SDK's builders rather than modifying the one
you get.

## Reacting to changes

For anything that changed on the Polkurier account, observe the [events](events.md). For changes to
the module's own data, observe Magento's model events listed there.

## Pricing

To change how an existing pricing model calculates, replace its entry in the `calculators` argument
of `Model\Pricing\PriceCalculatorPool`:

```xml
<type name="Polkurier\Shipping\Model\Pricing\PriceCalculatorPool">
    <arguments>
        <argument name="calculators" xsi:type="array">
            <item name="FIXED" xsi:type="object">Vendor\Module\Pricing\MyFixedPrice</item>
        </argument>
    </arguments>
</type>
```

Your class implements `Model\Pricing\PriceCalculatorInterface` and returns a price, or `null` to
leave the carrier out. The list of pricing models itself (`Enum\PricingModelEnum`) is
closed, because the carrier form is built from it. To adjust every price, for example to add a
surcharge, put a plugin on `PriceCalculatorPool::calculate()`.

## Offering methods conditionally

An `afterCollectRates` plugin on `Model\Carrier\Polkurier` can remove methods from the result, for
example for carts with oversized products or for certain customer groups.

## Checkout front end

The pickup point component and its template can be replaced or mixed into like any other checkout
component (see `view/frontend/layout/checkout_index_index.xml` and `requirejs-config.js`). The module
sets the shipping step's `shippingMethodItemTemplate` to its own copy of the method row, which adds
a `pickup-point-choice` region. If your theme or another module sets a different row template,
render that region in it too, or the picker won't show. The copy matches the original of
Magento_Checkout 100.4.7 to 100.4.9 (Magento 2.4.7 to 2.4.9; its header says so); after a Magento
upgrade, compare it with the new original.

The multi-address checkout's picker is in the bridge module, `polkurier/magento2-multishipping`:
`Polkurier\Multishipping\Block\PickupPoint` with the template `pickup-point.phtml`
(`Polkurier_Multishipping::pickup-point.phtml`); override the template in your theme as usual.

Whatever you change, keep the Polkurier name off the storefront.

## The New Shipment page

The label options are an ordinary block in the `extra_shipment_info` container
(`polkurier_label_options` in `adminhtml_order_shipment_new.xml`). Remove it with a layout update if
you don't want it; labels then use the default draft. The page can only set the draft fields in
`NativeLabel::OPTION_FIELDS`. To fill in anything else for labels, use the `DefaultDraftBuilder`
plugin above, which runs for labels too.

## Sync time source

Once you have a better source for when a remote entry changed, replace the
`Model\Sync\RemoteClockInterface` preference.

## Logging alongside the module

Inject `Polkurier\Shipping\Logger\Logger` (see [Logging](logging.md)) so your records land in the
same file and get the same masking.

Next: [Tests](tests.md)
