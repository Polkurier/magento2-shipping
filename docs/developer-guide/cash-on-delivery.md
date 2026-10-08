<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Cash on delivery payouts

[Developer guide](README.md) › Cash on delivery payouts

[`Model\Payout\PayoutManager`](../../src/Model/Payout/PayoutManager.php) wraps the SDK's money box
service. A payout requested on the **Request a payout** screen goes through it in these steps:

```
the payout form ............... starts from getConfig(), the account's payout settings, with the
        │                       orders whose cash is in the money box as of their last status
        │                       update; quote() prices it whenever the orders or payout type change
        │
"Request the payout"
        │
requestCode() ................. Polkurier sends the two-factor code; refused, with no code sent,
        │                       when the money box has nothing to pay out
        │
"Confirm the payout" .......... the code, and the PIN if the money box settings are locked with
        │                       one; "Send a new code" calls requestCode() again
        │
createPayout() ................ refuses a transfer without an account, or a request without the
        │                       code, before asking Polkurier
        ├── requested ......... the payout listing
        └── refused ........... the form again, with the choices kept and the code and PIN asked
                                for again

cancelPayout() ................ from the payout listing, while Polkurier hasn't sent the payout
```

The payout screens work with the installation's one Polkurier account. Payouts aren't stored
locally: the payout listing (`polkurier_payout_listing`) reads them from the account with
`getPayouts()`, a page at a time.

The confirmation code and PIN are never stored or logged, and the payout event doesn't include the
request that carried them.

Next: [Admin UI](admin-ui.md)
