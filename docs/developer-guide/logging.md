<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Logging

[Developer guide](README.md) › Logging

The module logs only to `var/log/polkurier`, through its own PSR logger,
[`Polkurier\Shipping\Logger\Logger`](../../src/Logger/Logger.php): a Monolog logger whose handler
and processor are set in `etc/di.xml`. It has none of Magento's handlers, so nothing is copied into
`system.log`. Inject it by type if your code works alongside the module, so your records land in the
same files and get the same masking:

```php
public function __construct(private readonly \Polkurier\Shipping\Logger\Logger $logger) {}
```

Its handler, [`Logger\Handler`](../../src/Logger/Handler.php), writes a pair of files a month:
records below a warning (requests and answers) to `YYYY-MM-access.log` (e.g. `2026-10-access.log`),
warnings and above (failed requests, the module's own problems) to `YYYY-MM-error.log`. Old months'
files aren't deleted. Secrets are always masked (see [Secrets](#secrets)), so with *Log personal
data* and *Save files* off, the files are safe to send to Polkurier support.

## What gets logged

The level says what happened: `warning` when Polkurier couldn't be asked and the module carried
on without it (checkout offered the domestic carriers, a point was kept unchecked), `error` when
something wasn't persisted or an order went on without its follow-up. The exception always goes in
the context as `exception`.

- **Always**: failures the merchant can't see, such as a Polkurier order placed while the Magento
  shipment failed, an observer of the module's events that threw, or a cron run that couldn't
  refresh a shipment. Checkout problems are logged too, since the customer is never shown them.
- **With *Log failed requests***: requests to Polkurier that failed, with the request and the
  answer.
- **With *Log all traffic of***: successful requests of the selected kinds, too. The default
  (`logging/methods` in `etc/config.xml`) is every method that changes the Polkurier account.

The request logging is the SDK's own, pointed at this logger and set by the **Logging** group:

- Personal data is hidden unless *Log personal data* is on.
- Files in requests and answers are left out. With *Save files* on, they're saved in the directory
  [`Sdk\LogFilesDirectoryResolver`](../../src/Model/Sdk/LogFilesDirectoryResolver.php) resolves
  from *Save files in*: a path relative to the Magento root or an absolute one,
  `var/log/polkurier/files` when empty, never under `pub`. Each month has its subdirectory
  (`2026/10`). Neither the module nor the SDK deletes them. A label holds the recipient's name and
  address even with *Log personal data* off.

## Secrets

[`Logger\TokenRedactingProcessor`](../../src/Logger/TokenRedactingProcessor.php) masks the API
token, two-factor and access tokens, security PINs, passwords and confirmation codes wherever they
appear as JSON fields: in the message, in string values of the context, and in the body of a PSR-7
request passed in the context. In context arrays, a value under a key with one of those names is
masked too. Other objects in the context aren't inspected, so don't put secrets in them. Records
reach any handler you add to this logger already masked. Don't log such values through another
logger.

Next: [Service contracts](service-contracts.md)
