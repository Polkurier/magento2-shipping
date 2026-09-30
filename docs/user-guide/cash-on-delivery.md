<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Cash on delivery

[User guide](README.md) › Cash on delivery

## Shipping with cash on delivery

1. Under **Cash on delivery payment methods** in the [configuration](configuration.md#shipments),
   select the payment methods that mean "the customer pays the courier", such as Magento's
   **Cash On Delivery**.
2. Add at least one bank account under **Polkurier > Settings > Bank accounts** (see
   [Addresses, bank accounts and pack templates](master-data.md#bank-accounts)), unless you have
   the money paid into your Polkurier money box or by postal order.
3. Choose the default payout time and where the money goes.

Orders paid with one of those methods are shipped with cash on delivery for the order total. You
can change the amount, or remove it, for each shipment. When an order ships in parts, each
shipment starts from what the earlier ones don't collect yet, so the customer isn't asked to pay
twice (see [Shipping an order in parts](shipping-an-order.md#shipping-an-order-in-parts)). At checkout, these payment methods are
hidden when the chosen carrier or pickup point can't collect cash.

Cash on delivery and insurance are in złoty. An order placed in another currency is converted at
the rate it was placed at when your store's base currency is the złoty. When neither the order nor
the base currency is the złoty, the amounts aren't filled in: enter them in złoty yourself.

Some carriers insist on insurance with cash on delivery. For those, the shipment is insured
automatically, for at least the amount it collects.

## Following the money

Each shipment with cash on delivery shows a **COD status** in the shipment lists:

| COD status | Meaning |
|---|---|
| Awaiting collection | the parcel hasn't been delivered yet |
| On its way to Polkurier | the courier collected the money and passes it on |
| In the money box | Polkurier has it, in your money box |
| Payout pending | a payout of it is being processed |
| Paid out | paid out to you |
| Settled | settled with Polkurier in another way |
| Compensation | offset against a compensation |

The status is updated along with the delivery status.

## Payouts

**Polkurier > Sales > Cash on delivery payouts** shows the payouts you've requested, with their
amount, fee, what was paid out, to which account, for how many shipments and their status.

To have money from your money box paid out, click **Request a payout**:

1. **Polkurier orders**: the shipments whose money you want paid out. Leave it empty to pay out
   everything available.
2. **Payout by**: bank transfer or postal order. For a transfer, enter the **Bank account
   number**.
3. **Fee paid**: how Polkurier's payout fee is paid, for example from the money box itself or on
   your invoice. Click **Show the fee** to see how much it is.
4. Click **Send me the confirmation code**. Polkurier sends you a code; enter it under
   **Confirmation code**. If your account protects payouts with a security PIN, enter it under
   **Security PIN** too.
5. Click **Request the payout**.

A payout that hasn't been processed yet can be cancelled from the list with **Cancel**; the money
then stays in your money box.

A payout request moves real money, so give the **Request payouts** permission only to people who
should be able to do that (see [Permissions](permissions.md)). The confirmation code and PIN are
never stored or logged.

Next: [Permissions](permissions.md)
