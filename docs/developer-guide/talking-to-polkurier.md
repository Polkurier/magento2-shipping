<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Talking to Polkurier

[Developer guide](README.md) › Talking to Polkurier

The module never builds HTTP requests itself. It uses the SDK's service interfaces
(`Polkurier\Webservice\Api\Service\*Interface`), and only those, so the SDK's facade and client
classes stay out of the module's code. If you need something the module doesn't do yet, go through
the same services.

## One client for the account

The module works with one Polkurier account for the whole installation, and an SDK service reads
its configuration once, when it's constructed. Injecting a service directly into a class would
build it with whatever credentials were saved at the time, and keep them after they change.

`Model\Sdk\ClientPool` solves this. It builds one SDK client for the account when it's first
needed, keeps it for the request, and hands out its services:

```php
public function __construct(private readonly ClientPool $clientPool) {}

public function execute(): void
{
    $carriers = $this->clientPool->courier()->availableCarriers();
}
```

| Method | SDK service |
|---|---|
| `account()` | account details, security PIN locks |
| `addresses()` | sender addresses |
| `bankAccounts()` | bank accounts |
| `courier()` | carriers, pickup points, pickup times |
| `geo()`, `utils()` | geographic lookups and helpers |
| `moneyBox()` | cash on delivery balance and payouts |
| `orders()` | placing, cancelling, labels, protocols, statuses, collective pickups |
| `packTemplates()` | pack templates |
| `twoFactorToken()` | sending two-factor codes |

The methods take no store: every store uses the same account. `ClientPool::reset()` drops the
cached client, so the next call builds it again; `Observer\CredentialsChanged` calls it when the
configuration is saved.

The pool builds the SDK through Magento's generated factories. Its configuration comes from
`Model\Sdk\AccountConfigProvider`, the SDK's `ConfigProviderInterface`, which reads the account
settings from the default scope. The serializer and validator are preferences in `etc/di.xml`
(`Model\Sdk\SerializerProvider`, `Model\Sdk\Validator`).

## The shipment gateway

The shipment side of the module (placing, cancelling, reconciling, statuses, labels and protocols,
prices, collection times and collective pickups) doesn't use the pool directly. It goes through
`Model\Sdk\ShipmentGatewayInterface` (implemented by `Model\Sdk\ShipmentGateway`), which sends
the SDK's request message and returns its answer, but lets no SDK exception through: a refusal or
failure becomes a `LocalizedException` with the reason described.

Placing an order is the one call where a failure may hide a success. When the request may have
arrived but no answer came back (a timeout while waiting, a dropped connection), the answer can't
be read, or Polkurier reports an unexpected failure of the kind that can follow a saved order,
`placeOrder()` throws `Model\Sdk\PlacementUncertainException` instead, and the shipment is marked
for reconciliation rather than failed. A refusal for a stated reason (the parcel's size, the
carrier's terms, the account's funds) and a connection that failed before the request went out (no
host, no connection, no TLS handshake, a timeout while connecting) fail the shipment at once, so it
can be corrected and sent again. The rest of the module (checkout, synchronization, cash on
delivery) uses the pool and handles the SDK's exceptions as below.

## Caching

A few things change rarely and are needed often, so they're cached in Magento's cache, for the
account:

- `Model\Carrier\CarrierCatalog`: the account's carriers and what each supports. `getCarriers()`
  narrows them to the *Available carriers* of the configuration, and everything that offers
  carriers uses it; only the refresh of the carrier settings and the *Available carriers* field
  itself use `getAccountCarriers()`, the whole list. The rule itself (nothing selected means all)
  is `Model\Carrier\AvailableCarriers`, which also narrows `CarrierConfigs::getActive()` and the
  admin's carriers list: checkout offers domestic carriers from their settings when the list
  can't be read, so the settings need it too;
- `Model\Checkout\MapTokenProvider`: the token the pickup point map needs, kept a little less
  long than it's valid. A failed or empty answer is kept for 90 seconds, since checkout asks for
  the token with every rate estimate.
- `Model\Carrier\CarrierNotices`: the carriers' notices shown on the shipment screen.
- `Model\Sdk\AccountCache`: small answers about the account asked for when an admin page opens,
  such as whether bank account changes need the PIN (15 minutes). The money box settings on the
  payout form are always read live, since they fill in where the money goes.

Every entry's key comes from `Model\Sdk\AccountCacheKey`: the account's customer ID and sandbox
flag, so changing the credentials or switching to the sandbox never serves the old account's
entries. The carrier list is also keyed by the SDK version, since a list cached by an earlier SDK
may no longer fit its classes, and it's cleared when the credentials change.

When the carrier list can't be fetched, the failure is kept for the rest of the request and for
90 seconds in the cache, and reported as `Model\Sdk\RecentFailureException` without asking again.
Checkout asks for the list several times per step, and would otherwise wait out the timeout each
time.

## What a carrier supports

`Model\Carrier\CarrierFeatures` answers every "can this carrier…" question from the carrier list:
pickup points (none, optional, required) and which point providers the map should show, cash on
delivery, insurance and whether it's required with cash on delivery, shipment types, collection
dates and times, drop-off, additional services and carrier-specific fields. Use it rather than
checking carrier codes; the checkout, both shipping pages and the draft builder all do.

## Errors

SDK calls throw subclasses of `Polkurier\Webservice\Exception\Exception`. The module catches them
by class where the difference matters:

- validation errors are shown per field on the shipment screen;
- connection errors and unreadable answers, where the outcome of a change is unknown, lead to
  reconciliation (see [Shipments](shipments.md#reconciliation)); for placing an order, the
  shipment gateway sorts them out;
- everything else is caught as the base class.

`Model\ErrorMessages::describe()` turns any of them into a message for the admin, including the
fields a validation error is about. Service methods throw `LocalizedException` with that message,
so controllers and REST callers only ever see Magento exceptions.

`ErrorMessages::isTransient()` tells whether asking again, unchanged, may get another answer; the
shipment screen offers **Try again** for prices and collection dates only then. It looks through
the `LocalizedException` to the SDK failure. A failed connection or unreadable answer qualifies, and
so do the codes for a Polkurier that is down or busy (`UNKNOWN_ERROR`, `MAINTENANCE_MODE`,
`TOO_MANY_REQUESTS`, `INTERNAL_SERVER_ERROR`). Every other code is a refusal of the request as it
is, and so is every uncoded refusal unless its wording says it passes ("spróbuj ponownie",
"chwilowo", "za chwilę", "później", an overload) or gives no reason. Most refusals are uncoded:
`ADDITIONAL_SERVICE_UNAVAILABLE` is used for weekend delivery only, and the carriers' own refusals
of a service, a size or a configuration arrive with code 0, worded by the carrier.

Don't let an SDK exception escape into checkout. The carrier and the pickup point code log the
error and carry on without Polkurier: no rates for carriers it can't vouch for, or no point check.

Next: [Configuration](configuration.md)
