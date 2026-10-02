<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Known limitations

[User guide](README.md) › Known limitations

- **Checkout themes.** The pickup point picker works with Magento's standard (Luma-based) checkout
  and the multi-address checkout. Other checkouts, such as Hyvä or one-step checkout modules,
  aren't supported yet.
- **Labels stored with Magento shipments are always PDF**, because that's what Magento stores,
  whatever **Label format** says. Labels printed from **Polkurier > Sales > Shipments** and the
  shipment details page follow the setting.
- **Magento's shipment page ships the address as it is.** To correct the recipient before placing
  a shipment, use the Polkurier shipment screen.
- **One Polkurier account per installation.** All websites share it; separate accounts per
  website aren't supported.
- **Only sender addresses are synced.** Recipient address book entries stay in the Polkurier panel.
- **Cash on delivery with a 16-day payout time** isn't available.
- **Carrier-specific fields** are plain text fields, even where the carrier expects one of a fixed
  set of values.
- **A cover address has no separate house number field.** The house number is added to the end of
  the street.
- **Changes on both sides between two syncs.** Until Polkurier reports when an address, bank account
  or template was last changed, a change made in the Polkurier panel counts as made when the module
  first notices it. If you edit the same entry on both sides between two syncs, the Magento edit may
  win even if it was made first.
- **Cancelling can't take back the shipment e-mail.** The Magento shipment is removed with the
  Polkurier order, but a customer who already got the "your order has shipped" e-mail keeps it.
  Add a comment to the order if they should know.
