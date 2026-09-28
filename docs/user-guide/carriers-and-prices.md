<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Carriers and prices

[User guide](README.md) › Carriers and prices

Everything about which carriers your customers can choose, what they're called and what they
cost is set under **Polkurier > Settings > Carriers**.

## Fetching your carriers

Click **Refresh carriers from Polkurier**. The module fetches the carriers available on your
account and adds any new ones to the list, switched off. Run it again whenever Polkurier adds a
carrier or your contract changes.

Only the carriers selected under **Available carriers** in the
[configuration](configuration.md#shipments) are listed; with none selected, all of them are.

A carrier your account no longer offers stays in the list with **Offered by the account** set to
No, so its settings aren't lost. It isn't shown at checkout while that's the case.

If you run several websites, each has its own list, since each can use a different account.
Filter by **Website** to see one.

## Setting up a carrier

Open a carrier from the list:

- **Offered in checkout** switches it on for customers.
- **Label in checkout** is the name customers see, for example "Kurier DPD" or "Paczkomat
  InPost". It's also what they see in e-mails and on their order page, and it becomes the title of
  the tracking numbers. Leave it empty to use the carrier's own name.
- **Label per store view** gives the carrier a different name in a particular store view, for
  example in English for an English store view. Click **Add**, pick the store view and type the
  label. Store views without a label of their own use the one above.
- **Logo in checkout** is shown in front of the method name at checkout. Click **Upload** and
  choose a JPG, PNG, GIF or WebP file; something around 60 × 30 pixels looks best, as the logo is
  shown at most 60 pixels wide and 24 high. Click the bin icon on the preview to remove it. The
  list of carriers shows each logo as a thumbnail.
- **Sort order** sets where the carrier appears among the other Polkurier methods. Lower numbers
  come first.

The label, **Offered in checkout** and the sort order can also be changed straight in the list:
click a carrier's row, edit, and click **Save** under it. The same goes for the price of carriers
with a fixed price, in the **Fixed price** column; tiered prices are edited on the carrier's page.
**Save and Continue Edit** on the carrier's page saves without taking you back to the list.

## Pricing

**Pricing** decides what the customer pays for shipping with the carrier. The price you set is
what the customer pays; what Polkurier charges you is a separate matter between you and
Polkurier.

- **Fixed price**: the same price for every cart.
- **By cart subtotal**: price tiers by the value of the cart.
- **By cart weight**: price tiers by the total weight of the products, in kilograms.
- **By cart subtotal and weight**: each tier has both a minimum value and a minimum weight, and a
  cart has to reach both.

With tiers, the customer gets the price of the highest tier the cart reaches. If the cart doesn't
reach any tier, the carrier isn't offered for that cart at all. That's useful on purpose (a
pallet carrier only for heavy carts, say), but usually you'll want your lowest tier to start at 0.

The subtotal is taken after discounts and without tax. The weight is the sum of the products'
weights as set in the catalog.

### Examples

**Free shipping above 200 zł**, 14.99 zł otherwise, with **By cart subtotal**:

| Subtotal from | Price |
|---|---|
| 0 | 14.99 |
| 200 | 0 |

**Heavier parcels cost more**, with **By cart weight**:

| Weight from [kg] | Price |
|---|---|
| 0 | 16.00 |
| 10 | 22.00 |
| 25 | 35.00 |

Carts over 25 kg pay 35 zł. If the carrier can't take parcels that heavy, don't add a tier for
them, and the carrier won't be offered for such carts.

Magento's cart price rules with free shipping still work: when a rule grants free shipping, every
Polkurier method is free.

## Pickup points

Carriers that deliver to parcel lockers or pickup shops automatically get the point picker at
checkout (see [Checkout](checkout.md)). There's nothing to set up per carrier.

## Other countries

Carriers that only deliver within Poland are offered only for Polish shipping addresses. For other
countries, only carriers that deliver abroad are offered. You can narrow this down further with
**Ship to Specific Countries** on the configuration page.

Next: [Addresses, bank accounts and pack templates](master-data.md)
