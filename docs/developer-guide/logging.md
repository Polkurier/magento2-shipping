<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Logging

[Developer guide](README.md) › Logging

The module logs to `var/log/polkurier.log` through a virtual Monolog logger,
`Polkurier\Shipping\Logger\Logger`, declared in `etc/di.xml`. Inject it by name if your code works
alongside the module:

```xml
<type name="Vendor\Module\Model\Something">
    <arguments>
        <argument name="logger" xsi:type="object">Polkurier\Shipping\Logger\Logger</argument>
    </arguments>
</type>
```

## What gets logged

- **Always**: failures the merchant can't see, such as a Polkurier order placed while the Magento
  shipment failed, an observer of the module's events that threw, or a cron run that couldn't
  refresh a shipment. Checkout problems are logged too, since the customer is never shown them.
- **With *Log failed requests***: requests to Polkurier that failed, with the request and the
  answer.
- **With *Log all traffic of***: successful requests of the selected kinds, too.

The request logging is the SDK's own; the module switches it on and points it at this logger,
with the settings of the **Logging** group: personal data hidden unless *Log personal data* is on,
and files in requests and answers left out, or with *Save files* saved in the directory
`Sdk\LogFilesDirectory` resolves (`var/log/polkurier-files` by default, never under `pub`). The
`polkurier_clean_log_files` cron job deletes saved files older than *Keep saved files for*, which
the SDK never does; it only touches files named as the SDK names them. The SDK never logs secrets
as they are: the module doesn't allow it.

## Secrets

`Logger\TokenRedactingProcessor` masks the API token, two-factor and access tokens, security PINs,
passwords and confirmation codes wherever they appear as JSON fields: in the message, in string
values of the context, and in the body of a PSR-7 request passed in the context, which is how the
SDK logs its requests. Arrays in the context are walked, and a value under a key with one of those
names is masked too. Other objects in the context aren't inspected, so don't put secrets in them. Keep in mind that records reach any
handler you add to this logger already masked, and don't log such values through another logger.

Next: [Service contracts and REST](rest-api.md)
