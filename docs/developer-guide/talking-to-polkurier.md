<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Talking to Polkurier

[Developer guide](README.md) › Talking to Polkurier

The module never builds HTTP requests itself. It uses only the SDK's service interfaces
(`Polkurier\Webservice\Api\Service\*Interface`). If you need something the module doesn't do, go
through the same services.

## One client for the account

The module works with one Polkurier account for the whole installation. An SDK service reads its
configuration once, when it's constructed, so a service injected directly would keep the
credentials saved at that time.

[`Model\Sdk\PolkurierClient`](../../src/Model/Sdk/PolkurierClient.php) (public interface
[`Api\PolkurierClientInterface`](../../src/Api/PolkurierClientInterface.php)) instead builds one SDK
client for the account when it's first needed, keeps it for the request, and hands out its services:

```php
public function __construct(private readonly PolkurierClient $polkurierClient) {}

public function execute(): void
{
    $carriers = $this->polkurierClient->getCourier()->availableCarriers();
}
```

| Method | SDK service |
|---|---|
| `getAccount()` | account details, security PIN locks |
| `getAddresses()` | sender addresses |
| `getBankAccounts()` | bank accounts |
| `getCourier()` | carriers, pickup points, pickup times |
| `getGeo()`, `getUtils()` | geographic lookups and helpers |
| `getMoneyBox()` | cash on delivery balance and payouts |
| `getOrders()` | placing, cancelling, labels, protocols, statuses, collective pickups |
| `getPackTemplates()` | pack templates |
| `getSystem()` | service information, the carriers' current notices, coupon validation |
| `getTwoFactorToken()` | sending two-factor codes |

The methods take no store: every store uses the same account. Saving the configuration resets the
client ([`PolkurierClient::reset()`](../../src/Model/Sdk/PolkurierClient.php)), so the next call
uses the new credentials. The client's configuration comes from
[`Model\Sdk\AccountConfigProvider`](../../src/Model/Sdk/AccountConfigProvider.php), the SDK's
`Polkurier\Webservice\Api\ConfigProviderInterface`, which reads the account settings from the
default scope.

## The shipment gateway

The shipment side of the module (placing, cancelling, reconciling, statuses, labels and protocols,
prices, collection times and collective pickups) goes through
[`Model\Sdk\ShipmentGateway`](../../src/Model/Sdk/ShipmentGateway.php). It lets no SDK exception
through: a refusal or failure becomes a `Magento\Framework\Exception\LocalizedException` that
describes the reason.

Placing an order is the one call where a failure may hide a success:

```
placeOrder()
├── accepted ................................ CreatedOrderMessage
├── may have arrived: no readable answer, ... PlacementUncertainException: the shipment is
│   or a failure that can follow a saved      marked for reconciliation rather than failed
│   order
└── refused for a stated reason, or the ..... LocalizedException: the shipment fails at
    connection failed before sending          once, so it can be corrected and sent again
```

[`Exception\PlacementUncertainException`](../../src/Exception/PlacementUncertainException.php) is a
`Magento\Framework\Exception\LocalizedException` too.

## Caching

Frequent lookups are cached per account in the module's own cache type, `polkurier`
([`Model\Cache\Type`](../../src/Model/Cache/Type.php)). It has its own line under **System > Cache
Management** and is switched on when the module is installed. When it's switched off, every lookup
asks Polkurier.

It holds the account's carriers and what each supports (for a day), pickup points looked up by id
(for a day), the pickup point map's token, the carriers' notices, small answers about the account
(15 minutes) and recent failures. Each kind has its own tag
([`Enum\Cache\CacheTagEnum`](../../src/Enum/Cache/CacheTagEnum.php)), and
[`Type::cleanTags()`](../../src/Model/Cache/Type.php) removes one kind and keeps the others.
[`Type::saveFailure()`](../../src/Model/Cache/Type.php) keeps a failure under its kind's tag and
the failures' tag, so cleaning either forgets it.

Entries are keyed by account, so changing the credentials never serves the old account's entries.
Saving the carrier settings cleans every kind that belongs to the account: all but the pickup
points, which are the same for any account.

The carrier list and the pickup points looked up by id go through the cache like this:

```
the carrier list, or a pickup point by id
├── in the cache ..................................... the cached answer, a point not found too
├── failed in the last 90 seconds .................... RecentFailureException, Polkurier not asked
└── otherwise, Polkurier is asked
    ├── it answers ................................... cached for a day
    └── it fails ..................................... the failure kept for 90 seconds, and thrown
```

[`Exception\RecentFailureException`](../../src/Exception/RecentFailureException.php) carries the
original failure's message, already worded for the merchant. A point isn't looked up while the
carrier list is failing either. For a point, only a failure to answer is kept: a refused point says
nothing of the next one. So checkout, which asks for the list several times per step, doesn't wait
out the timeout each time.

On the storefront, every request to Polkurier waits at most 5 seconds, whatever **Request timeout**
says. The limit is [`AccountConfigProvider`](../../src/Model/Sdk/AccountConfigProvider.php)'s
`maxTimeout`, set in `etc/frontend/di.xml`. It also applies when the status webhook reads the order.

## What a carrier supports

[`Model\Carrier\CarrierFeatureResolver`](../../src/Model/Carrier/CarrierFeatureResolver.php) answers
every "can this carrier…" question from the carrier list: pickup points (none, optional, required)
and their providers, cash on delivery, insurance, shipment types, collection dates and times,
drop-off, additional services and carrier-specific fields. Use it rather than checking carrier
codes.

## Errors

SDK calls throw subclasses of `Polkurier\Webservice\Exception\Exception`. The module's services
don't let them through: they throw `Magento\Framework\Exception\LocalizedException` with a message
for the admin, so callers only ever see Magento exceptions.

When you call the SDK yourself,
[`Model\Sdk\ErrorMessageResolver`](../../src/Model/Sdk/ErrorMessageResolver.php) gives you the same
handling. `createPhrase()` turns any failure into a message for the admin. `isTransient()` tells whether
sending the same request again may get a different answer; it also looks through a
`Magento\Framework\Exception\LocalizedException` to the failure behind it. Transient failures are
a failed connection, an unreadable answer, and the codes Polkurier uses when it's down or busy
(`UNKNOWN_ERROR`, `MAINTENANCE_MODE`, `TOO_MANY_REQUESTS`, `INTERNAL_SERVER_ERROR`). Most refusals
aren't.

No SDK exception escapes into checkout. When Polkurier can't be asked, the carrier and the pickup
point code log the error and carry on without it: they offer no rates for carriers the module can't
vouch for, or skip the point check.

Next: [Configuration](configuration.md)
