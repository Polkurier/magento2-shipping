<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Cash on delivery payouts

[Developer guide](README.md) › Cash on delivery payouts

`Model\MoneyBox\Payouts` wraps the SDK's money box service:

| Method | |
|---|---|
| `getConfig()` | the account's payout settings |
| `getPayouts()` | the payouts requested so far, paged |
| `quote()` | the fee for paying out given orders a given way |
| `requestCode()` | sends the two-factor code a payout needs |
| `createPayout()` | requests a payout, with the code and, if the account locks payouts, the PIN |
| `cancelPayout()` | cancels a payout that hasn't been processed |

The payout screens work with the installation's one Polkurier account and take no website.
`Model\MoneyBox\OrderNumbers` parses the Polkurier order numbers typed into the payout form, one
per line or separated by commas or spaces.

The payout listing (`polkurier_payout_listing`) reads the account directly through its data
provider; payouts aren't stored locally.

Requesting and cancelling payouts need the `Polkurier_Shipping::money_box_payout` ACL resource.
The confirmation code and PIN are never stored or logged, and the payout event doesn't include the
request that carried them.

Next: [Admin UI](admin-ui.md)
