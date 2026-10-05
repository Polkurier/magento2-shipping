<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Addresses, bank accounts and pack templates

[User guide](README.md) › Addresses, bank accounts and pack templates

These three live under **Polkurier > Settings**. They exist both in Magento and in your Polkurier
account, and the module keeps the two in step, so you can edit them in whichever place is handier.

## Addresses

Your sender addresses: where couriers collect parcels, and what's printed as the sender.

- **Label**: your own name for the address, shown in lists.
- **Name**, **Company**, **Street**, **House number**, **Flat number**, **Postcode**, **City**,
  **Province**, **Country**, **Phone**, **E-mail**: the address itself.
- **Sender drop-off point**: the point you usually drop parcels off at, for carriers that let you
  send from a point.
- **Default sender**: preselected for new shipments. Mark just one; if several are marked, the
  first one found is used.
- **Can be a cover address**: allows the address to be printed on labels instead of the real
  sender's, for example your brand's address when parcels leave a warehouse. You choose a cover
  address per shipment, as **Another sender on the label** under additional services, for carriers
  that offer it, or set one for every shipment in
  [Configuration](configuration.md).

If you haven't marked any address as the default, shipments can fall back to your store's address
or its shipping origin; see **Fall back to the store's address** and **Fall back to the shipping
origin** on the [configuration page](configuration.md#sender).

## Bank accounts

Where Polkurier pays out the cash on delivery money you collect.

- **Account name**: your own name for the account.
- **Account number**: the account's number.
- **Default**: preselected for shipments with cash on delivery.

Polkurier wants your confirmation for every new or changed bank account, so **Save** asks for it
in a window:

- If your account protects bank accounts with a security PIN, enter the PIN.
- Otherwise Polkurier sends you a confirmation code by text message as you click **Save**; the
  window says where it went and how long it's valid. Enter it and click **Confirm and save**.
  **Send a new code** sends another.

**Cancel** saves nothing. If Polkurier doesn't accept the confirmation, the account is still saved
in Magento, but shown as **Awaiting confirmation** until you open it and save it again. So is a
bank account saved while shipping (see
[Typing in a sender, an account or a parcel](shipping-an-order.md#typing-in-a-sender-an-account-or-a-parcel)). Changes made in the
Polkurier panel still come back with the sync in the meantime.

To delete a bank account protected by a PIN, open it and use **Delete** on the form: a window asks
for the PIN. Otherwise **Delete** in the list works as for the others. When you delete several at once,
the ones Polkurier refuses stay, with the reason shown, and the others are deleted.

## Pack templates

The parcels you ship most often, so you don't have to type sizes every time.

- **Template name** and **Description**.
- **Parcel type**: a standard, non-standard or long parcel, an envelope, or a pallet by its size:
  quarter (60 × 40), half (80 × 60), EURO (120 × 80, standard or non-standard), 120 × 100,
  120 × 120 or 150 × 150.
  The parcels make the shipment's type: envelopes go as an envelope shipment, pallets as a pallet
  shipment, anything else as parcels.
- **Length**, **Width** and **Height** in centimetres.
- **Maximum weight** in kilograms: the most a parcel of the template may weigh with what's in it.
  With **Parcel weight** set to the pack template's weight, it's also the weight a parcel of the
  template is sent with.
- **Tare** in kilograms, optional: what the packaging itself weighs. A parcel of the template that
  weighs what's in it weighs that and the tare, and packing automatically leaves room for it under
  the **Maximum weight**. It's kept in Magento only, since Polkurier's pack templates have no such
  field.
- **Cash on delivery**, **Insurance**, **Carrier** and **Additional services**: kept with the
  template in your Polkurier account, for use in the Polkurier panel.
- **Default for new shipments**: new shipments start with one parcel of this template, unless
  they're packed automatically (see [Packing automatically](shipping-an-order.md#packing-automatically)).

Templates with a size are also the boxes the module packs items into automatically. When
shipping, a parcel heavier than its template's **Maximum weight** is pointed out; whether packing
automatically also keeps parcels under it is set by **Pack template weight limits** (see
[Configuration](configuration.md)).

When you pick a template for a new parcel, the parcel's window opens with its type, size and
weight filled in, which you can still change for that parcel. On Magento's shipment page, each
package in the packaging window has a **Pack template** column before **Type** for this; a new
package starts with your default template, or **No template** when none is the default.

## Editing in the lists

You don't have to open an entry to change it: click a row in any of the three lists and edit its
fields in place, then click **Save** under the row. Tick several rows to edit them together. Each
change is sent to Polkurier as when you save the form, and if Polkurier refuses one, the row shows
why. A bank account changed in the list can't be confirmed there: it's saved in Magento, and a
message reminds you to open it and confirm it.

On the edit forms, **Save and Continue Edit** saves without taking you back to the list.

Search by keyword above each list finds addresses by their label, name, company, street,
postcode, town, phone or e-mail; bank accounts by their name or number; pack templates by their
name. Labels and names are unique within each list: saving one another entry already has, in
any letters' case, is refused.

## Keeping Magento and Polkurier in step

- **Saving in Magento** sends the change to Polkurier straight away.
- **Changes made in the Polkurier panel** come back with the hourly sync, or at once if you click
  **Synchronize with Polkurier** on any of these three pages.
- **If the same entry changed on both sides** since the last sync, the more recent change wins.
- **Deleting in Magento**, with **Delete** in an entry's row or for several entries at once from
  the **Actions** menu, deletes them in Polkurier too.
- **Deleting in Polkurier** removes the entry from Magento, unless you edited it in Magento in the
  meantime; then your edit is sent back to Polkurier.

The lists show each entry's **Synchronization** state:

| State | Meaning |
|---|---|
| In sync | Magento and Polkurier agree |
| Waiting to be sent | changed in Magento, not sent yet (usually because Polkurier couldn't be reached); the next sync sends it |
| Error | Polkurier refused the change; the reason is under **Sync error** |
| Awaiting confirmation | a bank account change that still needs your PIN or confirmation code |

The lists also show when each entry was **Last synchronized**. The **Sync error** and **Polkurier ID**
columns are hidden at first; turn them on under **Columns**.

Next: [Checkout](checkout.md)
