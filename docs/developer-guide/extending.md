<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Extending the module

[Developer guide](README.md) › Extending the module

Prefer plugins on the service contracts and on the public methods listed here. Everything else
counts as internal and may change between versions.

## Changing the default shipment

Add an `afterCreateFromOrder` plugin on
[`Model\Shipment\Draft\DefaultDraftBuilder`](../../src/Model/Shipment/Draft/DefaultDraftBuilder.php)
to change what a new shipment starts with: a different sender per store, extra services for certain
products, parcels computed from the cart. The shipment screen, Magento's label flow and
[`ShipmentManagementInterface::getDefaultDraft()`](../../src/Api/ShipmentManagementInterface.php)
all pick it up. The label flow takes only the options from it; its parcels come from the
packaging popup.

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

Service codes are the values of the SDK's `Polkurier\Webservice\Enum\Order\AdditionalServiceEnum`.

## Changing what's sent

An `afterMap` plugin on
[`Model\Shipment\Placement\CreateOrderMessageMapper`](../../src/Model/Shipment/Placement/CreateOrderMessageMapper.php)
sees the final `Polkurier\Webservice\Request\Order\CreateOrderMessage` just before it goes out,
whichever way the shipment was placed. The SDK's messages are immutable, so build a new one with the
SDK's builders rather than modifying the one you get.

Every message that has an SDK builder is built through a fresh builder from Magento's generated
factory (for example `Polkurier\Webservice\Builder\Address\SaveMessageBuilderFactory`). So a
plugin on a builder reaches every message of its kind: a `beforeBuild` plugin can set a field, an
`afterBuild` plugin can return another message, and a preference can swap the builder for a
subclass.

```php
public function beforeBuild(\Polkurier\Webservice\Builder\Address\SaveMessageBuilder $subject): void
{
    $subject->province('mazowieckie');
}
```

Smaller messages without a builder (an id, a delete) are created directly.

## Calling Polkurier from your own code

[`Api\PolkurierClientInterface`](../../src/Api/PolkurierClientInterface.php) hands out every
service of the SDK for the account the module is connected to, with its credentials, sandbox
setting, timeouts and logging, including those the module doesn't use itself, such as `getInvoices()`,
`getPayments()` and `getTickets()`:

```php
$point = $this->polkurierClient->getCourier()->getCourierPoint('WAW195M', ['INPOST_PACZKOMAT']);
```

Orders placed this way aren't the module's shipments: it won't list, follow or cancel them. To place
a shipment for a Magento order, use
[`ShipmentManagementInterface`](../../src/Api/ShipmentManagementInterface.php).

## Reacting to changes

For anything that changed on the Polkurier account, observe the [events](events.md). For changes to
the module's own data, observe Magento's model events listed there.

## Pricing

To change how an existing pricing model calculates, replace its entry in the `calculators` argument
of
[`Model\Carrier\Pricing\PriceCalculatorPool`](../../src/Model/Carrier/Pricing/PriceCalculatorPool.php):

```xml
<type name="Polkurier\Shipping\Model\Carrier\Pricing\PriceCalculatorPool">
    <arguments>
        <argument name="calculators" xsi:type="array">
            <item name="FIXED" xsi:type="object">Vendor\Module\Pricing\MyFixedPrice</item>
        </argument>
    </arguments>
</type>
```

Your class implements
[`Model\Carrier\Pricing\PriceCalculatorInterface`](../../src/Model/Carrier/Pricing/PriceCalculatorInterface.php):
`calculate(CarrierConfigInterface $config, float $subtotal, float $weight): ?float` gets the
carrier's settings, the cart's subtotal in the base currency and its weight in kilograms, and
returns a price, or `null` to leave the carrier out. The list of pricing models itself
([`Enum\CarrierConfig\PricingModelEnum`](../../src/Enum/CarrierConfig/PricingModelEnum.php)) is
closed, because the carrier form is built from it. To adjust every price, for example to add a
surcharge, put a plugin on
[`PriceCalculatorPool::calculate()`](../../src/Model/Carrier/Pricing/PriceCalculatorPool.php).

## Offering methods conditionally

An `afterCollectRates` plugin on [`Model\Carrier\Polkurier`](../../src/Model/Carrier/Polkurier.php)
can remove methods from the result, for example for carts with oversized products or for certain
customer groups.

## Checkout front end

The pickup point component and its template can be replaced or mixed into like any other checkout
component (see `view/frontend/layout/checkout_index_index.xml` and `requirejs-config.js`). The module
sets the shipping step's `shippingMethodItemTemplate` to its own copy of the method row, which adds
a `pickup-point-choice` region. If your theme or another module sets a different row template,
render that region in it too, or the picker won't show. The copy matches the original in
Magento_Checkout 100.4.7 to 100.4.9 (Magento 2.4.7 to 2.4.9).

The multi-address checkout's picker is in the bridge module, `polkurier/magento2-multishipping`:
`Polkurier\Multishipping\Block\PickupPoint` with the template `pickup-point.phtml`
(`Polkurier_Multishipping::pickup-point.phtml`), which a theme can override.

## The New Shipment page

The label options are an ordinary block in the `extra_shipment_info` container
(`polkurier_label_options` in `adminhtml_order_shipment_new.xml`). Remove it with a layout update if
you don't want it; labels then use the default draft. The page can only set the draft fields in
[`Native\NativeLabelService::OPTION_FIELDS`](../../src/Model/Shipment/Native/NativeLabelService.php).
To fill in anything else for labels, use the
[`DefaultDraftBuilder`](../../src/Model/Shipment/Draft/DefaultDraftBuilder.php) plugin above, which
runs for labels too.

## Logging alongside the module

Inject [`Polkurier\Shipping\Logger\Logger`](../../src/Logger/Logger.php) (see [Logging](logging.md))
so your records land in the same files and get the same masking.

Next: [Tests](tests.md)
