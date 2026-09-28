<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Polkurier for Magento 2: developer guide

This guide explains how the `Polkurier_Shipping` module is built and where you can hook into it.
It assumes you know Magento 2 module development: dependency injection, plugins and observers, UI
components, declarative schema and service contracts.

It doesn't document the Polkurier web service itself. All communication goes through the
[Polkurier SDK](https://packagist.org/packages/polkurier/polkurier-sdk), so look at the SDK when you
need to know what a request or response contains.

For installation and everyday use, see the [user guide](../user-guide/README.md).

## Chapters

1. [Package layout](package-layout.md): where things are
2. [Talking to Polkurier](talking-to-polkurier.md): the SDK, one client per store, errors
3. [Configuration](configuration.md): reading settings, scopes, secrets
4. [Database](database.md): tables and what they hold
5. [Checkout](checkout.md): the carrier, pricing and pickup points
6. [Shipments](shipments.md): drafts, placing, reconciliation, statuses
7. [Shipping labels in Magento](magento-labels.md): the integration with Magento's own label flow
8. [Synchronization](synchronization.md): addresses, bank accounts and pack templates
9. [Cash on delivery payouts](cash-on-delivery.md)
10. [Admin UI](admin-ui.md): screens, controllers, ACL
11. [Scheduled jobs](cron.md)
12. [Logging](logging.md)
13. [Service contracts and REST](rest-api.md)
14. [Events](events.md)
15. [Extending the module](extending.md): the supported extension points
16. [Tests](tests.md)

## Principles worth knowing up front

- **The storefront never shows the Polkurier name.** Carrier titles are empty, methods are titled
  with the merchant's labels, and track titles use the shipping method's name. Keep it that way in
  anything you add to the frontend, including page source strings. The carrier code `polkurier`
  and the module name are fine.
- **Checkout never waits on or breaks because of Polkurier.** Anything that talks to Polkurier
  during checkout degrades gracefully and logs.
- **A shipment is never sent twice blindly.** Placing an order with Polkurier isn't idempotent, so
  an unconfirmed attempt is reconciled before another one is allowed.
- **Secrets stay secret.** The API token, security PINs, two-factor tokens and codes are never
  stored in plain text, logged unmasked, persisted in form data or put into events.
- **Every change on the Polkurier account dispatches an event**, whichever path caused it.
