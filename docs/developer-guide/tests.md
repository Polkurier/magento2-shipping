<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Tests

[Developer guide](README.md) › Tests

Unit tests are in `src/Test/Unit`, mirroring the source tree. Run them with Magento's unit test
configuration from the Magento root:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/polkurier/magento2-shipping/src/Test/Unit
```

A warning about Magento's Allure reporting extension missing its config file comes from Magento's
test configuration and doesn't affect the results.

## How they're written

- **No request leaves the machine.** Tests that go through the SDK use its `RecordingClient`
  (`Polkurier\Webservice\Testing\RecordingClient`), which answers with prepared responses and
  records what would have been sent. They check both what comes back and the request payload.
  `Test/Unit/Support/SdkTestCase` sets up an SDK client around it.
- **Recorded answers** live in `Test/Unit/_files` (`get_orders.json` is one order as Polkurier lists
  it, read by the snapshot and details tests).
- **Events** are checked with `Test/Unit/Support/RecordingEventManager`, which records dispatched
  events and can be made to fail, to prove a failing observer doesn't undo a change.
- **Time** comes from Magento's `Stdlib\DateTime\DateTime` wherever the module reads it, so tests
  pass `Test/Unit/Support/FixedDateTime`, stopped at a given moment (or running, when given none).
- **Magento models** are built with stubbed resources rather than the object manager helper, so the
  tests stay fast and don't need a database.
- **Admin actions** extend `Test/Unit/Support/AdminActionTestCase`, which builds the backend
  context with a request answering from `$params` and `$post`, and keeps the messages the action
  adds, the redirect and the JSON it answers with.
- **Synchronization** is driven through `Support/FakeSyncAdapter` and `FakePushableSyncAdapter`.

## What's covered

The parts where a mistake costs money or data have the most tests: placing, reconciliation and
duplicates (`ShipmentManagementTest`, `ReconciliationServiceBatchTest`), when a shipment counts as finished
(`RemoteOrderApplierTest`), the status refresh (`StatusRefreshServiceBatchTest`), Magento's label flow
(`NativeLabelServiceTest`, `PrepareLabelledShipmentTest`), the sync engine and bank account confirmation
(`SyncEngineTest`, `PublisherTest`, `BankAccountHandlerTest`), labels, protocols and collective
pickups (`DocumentServiceTest`, `PickupServiceTest`), the admin actions that place, pack and save
(`ShipmentActionsTest`, `SyncableEntityActionsTest`, `RedirectingActionsTest`), payload mapping (`CreateOrderMessageMapperTest`,
`AddressBookMapperTest`, `StreetParserTest`), pricing (`TiersTest`, `PolkurierTest`) and secret
masking (`TokenRedactingProcessorTest`). The multi-address checkout's tests are in the bridge
module, `polkurier/magento2-multishipping`.

Around them: the checkout and order observers (cash on delivery per carrier, point and address,
point validation and copying, the order's shipping following its Polkurier orders), the scheduled
jobs, the carrier refresh, the status webhook (`StatusWebhookTest`: signature, matching, refusals),
cancelling, refreshing and linking by hand (`CancellationServiceTest`, `StatusRefreshServiceTest`,
`ReconciliationServiceTest`), the grid columns and the admin buttons' scripts (`OnClickTest`), and two
sweeps over the code itself: `ConfigTest` reads every setting and checks it against the scopes
the configuration page offers, and `AdminActionsAclTest` checks every admin action declares its
own ACL resource, one that exists.

A few controller tests cover a group of actions rather than one class, named for what they share:
`ShipmentActionsTest`, `FollowingShipmentsActionsTest`, `SyncableEntityActionsTest`,
`RedirectingActionsTest` and `SyncableEntityAclTest`.

When you extend the module, the same helpers work for your own tests: build the module's services
with a `RecordingClient`-backed `PolkurierClient` and assert on the payload.
