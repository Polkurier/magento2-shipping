<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Polkurier for Magento 2: developer guide

All communication with Polkurier goes through the
[Polkurier SDK](https://packagist.org/packages/polkurier/polkurier-sdk); the SDK shows what each
request and response contains.

See also the [user guide](../user-guide/README.md).

## Chapters

1. [Installation](installation.md): requirements, installing, cron, updating and removing
2. [Package layout](package-layout.md): where things are
3. [Talking to Polkurier](talking-to-polkurier.md): the SDK, one client for the account, errors
4. [Configuration](configuration.md): reading settings, scopes, secrets
5. [Database](database.md): tables and what they hold
6. [Checkout](checkout.md): the carrier, pricing and pickup points
7. [Shipments](shipments.md): drafts, placing, reconciliation, statuses
8. [Shipping labels in Magento](magento-labels.md): the integration with Magento's own label flow
9. [Synchronization](synchronization.md): addresses, bank accounts and pack templates
10. [Cash on delivery payouts](cash-on-delivery.md)
11. [Admin UI](admin-ui.md): screens, controllers, ACL
12. [Scheduled jobs](cron.md)
13. [Logging](logging.md)
14. [Service contracts](service-contracts.md)
15. [Events](events.md)
16. [Extending the module](extending.md): the supported extension points
17. [Tests](tests.md)

## Principles worth knowing up front

- **Checkout never waits on or breaks because of Polkurier.** Anything that talks to Polkurier
  during checkout degrades gracefully and logs.
- **A shipment is never sent twice blindly.** Placing an order with Polkurier isn't idempotent, so
  an unconfirmed attempt is reconciled before another one is allowed.
- **Secrets stay secret.** The API token, security PINs, two-factor tokens and codes are never
  stored in plain text, logged unmasked, persisted in form data or put into events.
- **Every change on the Polkurier account dispatches an event**, whichever path caused it.
- **Each request starts afresh.** Services that keep state for a request implement
  `Magento\Framework\ObjectManager\ResetAfterRequestInterface`, so the module also runs under an
  application server.
