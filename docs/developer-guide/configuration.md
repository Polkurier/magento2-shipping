<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Configuration

[Developer guide](README.md) › Configuration

`Model\Config\ConfigProvider` is the only class that reads the module's own settings; the carrier's active flag
goes through `AbstractCarrier`, as for any carrier, and `Config\Source\ShippingCarrier` lists the
store's carriers from the `carriers` section. The module works
with one Polkurier account for the whole installation, so the account's settings (credentials,
sandbox, timeout, logging, available carriers, synchronization) are global: their getters take no
store and read the default scope only. The admin shows them at the default scope only, so no
value is saved for a website or store view. Every other getter takes an optional store id and reads at store scope,
which also resolves website and default values. A null store id means the current store, which in
the admin is the default scope.

The settings live under the carrier's section, `carriers/polkurier/`, so Magento treats the module
as a regular delivery method.

| Path (after `carriers/polkurier/`) | Getter | Scope |
|---|---|---|
| `active` | `isActive()` | website |
| `title` | `getTitle()` | store view |
| `checkout/tier_cart_value` | `getTierCartValue()`: `Enum\CartValueEnum`, `gross` (default) or `net`, see `RateCollector::cartValueOf()` | website |
| `general/customer_id`, `general/api_token` | `getLogin()`, `getToken()`, `hasCredentials()` | global, encrypted |
| `general/sandbox_mode` | `isSandbox()` | global |
| `general/api_url` | `getApiUrl()` | global, not shown in the admin |
| `general/timeout` | `getTimeout()` | global |
| `general/external_order_id` | `getExternalOrderId()` | global; `order` (default) or `shipment`, see `Placement\ExternalIdService` |
| `general/application_id` | `getApplicationIdOverride()` | global; empty (default): made from the encryption key, see `Sdk\ApplicationIdProvider`; `Backend\ApplicationId` allows up to 50 of `[A-Za-z0-9._-]` |
| `general/available_carriers` | `getAvailableCarriers()` | global |
| `logging/failed_requests`, `logging/methods` | `isLoggingEnabled()`, `getMethodsToLog()` | global |
| `logging/personal_data`, `logging/files`, `logging/files_directory` | `isPersonalDataLoggingEnabled()`, `isFileLoggingEnabled()`, `getLogFilesDirectory()` | global; the directory is resolved by `Sdk\LogFilesDirectoryResolver` |
| `shipment_defaults/default_shipment_type` | `getDefaultShipmentType()` | website |
| `sender/use_store_information_address`, `sender/use_store_origin_address`, `sender/default_sender_email` | `isUseStoreInformationAddress()`, `isUseStoreOriginAddress()`, `getSenderEmailIdentity()` | website |
| `shipment_defaults/default_additional_services` | `getDefaultServices()` | website |
| `shipment_defaults/insure_by_default` | `isInsureByDefault()` | website |
| `packing/parcel_weight` | `getParcelWeight()`: `ParcelWeightEnum`, whose `weigh()` is the rule; `rules.parcelWeight()` is its copy for the screens | website |
| `packing/pack_new_shipments` | `isPackingNewShipments()` | website |
| `packing/packing_margin` | `getPackingMargin()` | website |
| `packing/weight_limit` | `getWeightLimit()` | website |
| `cod/cod_payment_methods` | `getCodPaymentMethods()` | website |
| `cod/default_cod_type`, `cod/default_cod_refund_type` | `getDefaultCodType()`, `getDefaultCodReturnType()` | website |
| `shipment_defaults/description_format` | `getDescriptionFormat()` | store view |
| `labels/label_format` | `getLabelFormat()` | website |
| `labels/notify_customer` | `isNotifyCustomer()` | website |
| `labels/native_labels` | `isNativeLabelsEnabled()` | website |
| `labels/shipment_screen` | `isShipmentScreenEnabled()` | website |
| `map/default_latitude`, `map/default_longitude`, `map/default_zoom` | `getMapLatitude()` … | store view |
| `map/show_list`, `map/show_search_input` | `isMapShowList()`, `isMapShowSearch()` | store view |
| `map/required_point_functions` | `getMapPointFunctions()` | website |
| `map/url` | `getMapUrl()` | global, not shown in the admin |
| `sync/enabled`, `sync/status_enabled` | `isSyncEnabled()`, `isStatusSyncEnabled()` | global |
| `sync/webhook_secret` | `getWebhookSecret()` | global |

Magento's standard carrier settings (`sallowspecific`, `specificcountry`, `showmethod`,
`specificerrmsg`, `sort_order`) are read by Magento itself.

Getters that return choices return the SDK's enums (`getDefaultShipmentType()`,
`getDefaultCodType()`, `getLabelFormat()` and so on), so you never compare raw strings.

## Secrets

The customer ID, the token and the status webhook's secret use Magento's encrypted backend model
and are marked sensitive in `etc/di.xml`, so `app:config:dump` never writes them to
`app/etc/config.php`. `ConfigProvider::getLogin()`, `getToken()` and `getWebhookSecret()` decrypt them.
Nothing else should read those paths.

You can set them from the command line or in `env.php` like any sensitive setting:

```bash
bin/magento config:set --lock-env carriers/polkurier/general/customer_id 12345
```

## API and map hosts

`general/api_url` and `map/url` are empty by default and not shown in the admin. Empty means the
SDK picks Polkurier's production or sandbox host from `sandbox_mode`, and the map uses the matching
map host. Set them only to point a development installation at another environment.

The map host is whitelisted for scripts, styles, frames and connections in `etc/csp_whitelist.xml`.

## Where each part of the configuration is used

- Account settings: `Model\Sdk\AccountConfigProvider`, for the SDK client.
- Shipment defaults: `Model\Shipment\Draft\DefaultDraftBuilder`.
- `native_labels`: the carrier's `isShippingLabelsAvailable()`, the New Shipment page block and
  the order page buttons. `shipment_screen`: the order page buttons.
- Map settings: `Model\PickupPoint\MapConfigProvider`.
- Sync switches: the cron jobs.

Next: [Database](database.md)
