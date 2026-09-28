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

## One client per store

Each website can have its own Polkurier account, and an SDK service reads its configuration once,
when it's constructed. Injecting a service directly into a class would tie it to whatever store
happened to be current at the time, which in the admin is the admin store and in cron is none at
all.

`Model\Sdk\ClientPool` solves this. It keeps one SDK client per store and hands out its services:

```php
public function __construct(private readonly ClientPool $clientPool) {}

public function execute(int $storeId): void
{
    $carriers = $this->clientPool->courier($storeId)->availableCarriers();
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

Always pass the store the data belongs to: the order's store for anything about an order, and for
website-level data a store of that website. `Model\WebsiteScope::getStoreId($websiteId)` gives you
the website's default store view. `ClientPool::reset()` drops the cached clients;
`Observer\CredentialsChanged` calls it when the configuration is saved.

The pool builds the SDK through Magento's generated factories. Its configuration comes from
`Model\Sdk\StoreConfigProvider`, the SDK's `ConfigProviderInterface` bound to one store id. The
serializer and validator are preferences in `etc/di.xml` (`Model\Sdk\SerializerProvider`,
`Model\Sdk\Validator`).

## Caching

A few things change rarely and are needed often, so they're cached in Magento's cache, per account:

- `Model\Carrier\CarrierCatalog`: the account's carriers and what each supports. `getCarriers()`
  narrows them to the *Available carriers* of the configuration, and everything that offers
  carriers uses it; only the refresh of the carrier settings and the *Available carriers* field
  itself use `getAccountCarriers()`, the whole list;
- `Model\Checkout\MapTokenProvider`: the token the pickup point map needs, kept a little less
  long than it's valid.

The carrier list is cleared when the credentials change; the map token is keyed by account, so a
new account gets a new one.

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
  reconciliation (see [Shipments](shipments.md#reconciliation));
- everything else is caught as the base class.

`Model\ErrorMessages::describe()` turns any of them into a message for the admin, including the
fields a validation error is about. Service methods throw `LocalizedException` with that message,
so controllers and REST callers only ever see Magento exceptions.

Don't let an SDK exception escape into checkout. The carrier and the pickup point code log the
error and carry on without Polkurier: no rates for carriers it can't vouch for, or no point check.

Next: [Configuration](configuration.md)
