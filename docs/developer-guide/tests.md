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

## Helpers

These work for your own tests too:

- **No request leaves the machine.** Tests that go through the SDK use its
  `Polkurier\Webservice\Testing\RecordingClient`, which answers with prepared responses and records
  what would have been sent, so a test can check both the result and the request payload.
  [`Test/Unit/Support/SdkTestCase`](../../src/Test/Unit/Support/SdkTestCase.php) sets up an SDK
  client around it; build the module's services with a
  [`PolkurierClient`](../../src/Model/Sdk/PolkurierClient.php) backed by it. Recorded answers live
  in `Test/Unit/_files`.
- **Events** are checked with
  [`Test/Unit/Support/RecordingEventManager`](../../src/Test/Unit/Support/RecordingEventManager.php),
  which records dispatched events and can be made to fail.
- **Time** comes from `Magento\Framework\Stdlib\DateTime\DateTime` wherever the module reads it, so
  tests pass [`Test/Unit/Support/FixedDateTime`](../../src/Test/Unit/Support/FixedDateTime.php),
  stopped at a given moment.
- **Admin actions** extend
  [`Test/Unit/Support/AdminActionTestCase`](../../src/Test/Unit/Support/AdminActionTestCase.php),
  which builds the backend context with a request that answers from `$params` and `$post`, and
  keeps the messages, the redirect and the JSON response.
- **Synchronization** is driven through
  [`Support/FakeSyncAdapter`](../../src/Test/Unit/Support/FakeSyncAdapter.php) and
  [`FakePushableSyncAdapter`](../../src/Test/Unit/Support/FakePushableSyncAdapter.php).

Magento models are built with stubbed resources rather than the object manager helper, so the
tests need no database.

## What's covered

The most tests go where a mistake costs money or data: placing, reconciliation and duplicates,
when a shipment counts as finished, the status refresh and webhook, Magento's label flow, the sync
engine and bank account confirmation, documents and collective pickups, payload mapping, pricing and
secret masking. [`AdminActionsAclTest`](../../src/Test/Unit/Controller/Adminhtml/AdminActionsAclTest.php)
checks that every admin action declares an existing ACL resource of its own, and
[`ConfigProviderTest`](../../src/Test/Unit/Model/Config/ConfigProviderTest.php) checks every setting
against the scopes the configuration page offers. The multi-address checkout's tests are in the
bridge module, `polkurier/magento2-multishipping`.
