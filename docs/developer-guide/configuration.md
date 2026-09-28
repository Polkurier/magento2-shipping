<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Configuration

[Developer guide](README.md) › Configuration

`Model\Config` is the only class that reads the module's system configuration. Every getter takes
an optional store id and reads at store scope, which also resolves website and default values. A
null store id means the current store, which in the admin is the default scope. For website-level
settings, pass a store of that website (`Model\WebsiteScope::getStoreId()`).

The settings live under the carrier's section, `carriers/polkurier/`, so Magento treats the module
as a regular delivery method.

| Path (after `carriers/polkurier/`) | Getter | Scope |
|---|---|---|
| `active` | `isActive()` | website |
| `title` | `getTitle()` | store view |
| `general/customer_id`, `general/api_token` | `getLogin()`, `getToken()`, `hasCredentials()` | website, encrypted |
| `general/sandbox_mode` | `isSandbox()` | website |
| `general/api_url` | `getApiUrl()` | not shown in the admin |
| `general/timeout` | `getTimeout()` | default |
| `general/logging`, `general/log_methods` | `isLoggingEnabled()`, `getMethodsToLog()` | website |
| `shipments/available_carriers` | `getAvailableCarriers()` | website |
| `shipments/default_carrier` | `getDefaultCarrier()` | website |
| `shipments/default_shipment_type` | `getDefaultShipmentType()` | website |
| `shipments/use_store_information_address`, `shipments/use_store_origin_address`, `shipments/default_sender_email` | `isUseStoreInformationAddress()`, `isUseStoreOriginAddress()`, `getSenderEmailIdentity()` | website |
| `shipments/default_additional_services` | `getDefaultServices()` | website |
| `shipments/insure_by_default` | `isInsureByDefault()` | website |
| `shipments/cod_payment_methods` | `getCodPaymentMethods()` | website |
| `shipments/default_cod_type`, `shipments/default_cod_refund_type` | `getDefaultCodType()`, `getDefaultCodReturnType()` | website |
| `shipments/description_format` | `getDescriptionFormat()` | store view |
| `shipments/label_format` | `getLabelFormat()` | website |
| `shipments/notify_customer` | `isNotifyCustomer()` | website |
| `shipments/native_labels` | `isNativeLabelsEnabled()` | website |
| `shipments/shipment_screen` | `isShipmentScreenEnabled()` | website |
| `map/default_latitude`, `map/default_longitude`, `map/default_zoom` | `getMapLatitude()` … | store view |
| `map/show_list`, `map/show_search_input` | `isMapShowList()`, `isMapShowSearch()` | store view |
| `map/required_point_functions` | `getMapPointFunctions()` | website |
| `map/url` | `getMapUrl()` | not shown in the admin |
| `sync/enabled`, `sync/status_enabled` | `isSyncEnabled()`, `isStatusSyncEnabled()` | default |

Magento's standard carrier settings (`sallowspecific`, `specificcountry`, `showmethod`,
`specificerrmsg`, `sort_order`) are read by Magento itself.

Getters that return choices return the SDK's enums (`getDefaultShipmentType()`,
`getDefaultCodType()`, `getLabelFormat()` and so on), so you never compare raw strings.

## Secrets

The customer ID and token use Magento's encrypted backend model and are marked sensitive in
`etc/di.xml`, so `app:config:dump` never writes them to `app/etc/config.php`. `Config::getLogin()`
and `getToken()` decrypt them. Nothing else should read those paths.

You can set them from the command line or in `env.php` like any sensitive setting:

```bash
bin/magento config:set --scope=websites --scope-code=base --lock-env carriers/polkurier/general/customer_id 12345
```

## API and map hosts

`general/api_url` and `map/url` are empty by default and not shown in the admin. Empty means the
SDK picks Polkurier's production or sandbox host from `sandbox_mode`, and the map uses the matching
map host. Set them only to point a development installation at another environment.

The map host is whitelisted for scripts, styles, frames and connections in `etc/csp_whitelist.xml`.

## Where each part of the configuration is used

- Account settings: `Model\Sdk\StoreConfigProvider`, for every SDK client.
- Shipment defaults: `Model\Shipment\DefaultDraftBuilder`.
- `native_labels`: the carrier's `isShippingLabelsAvailable()`, the New Shipment page block and
  the order page buttons. `shipment_screen`: the order page buttons.
- Map settings: `Model\Checkout\MapConfigProvider`.
- Sync switches: the cron jobs.

Next: [Database](database.md)
