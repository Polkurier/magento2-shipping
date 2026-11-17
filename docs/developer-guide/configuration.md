<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Configuration

[Developer guide](README.md) › Configuration

[`Model\Config\ConfigProvider`](../../src/Model/Config/ConfigProvider.php) reads the module's own
settings. The carrier's active flag goes through `Magento\Shipping\Model\Carrier\AbstractCarrier`,
as for any carrier. The settings live under the carrier's section, `carriers/polkurier/`, so Magento
treats the module as a regular delivery method.

## Global settings

The module works with one Polkurier account for the whole installation, so the account's settings,
and those that go with them, are global: their getters take no store, and the admin shows them at
the default scope only.

| Path (after `carriers/polkurier/`) | Getter |
|---|---|
| `general/customer_id`, `general/api_token` | `getLogin()`, `getToken()`, `hasCredentials()` |
| `general/sandbox_mode` | `isSandbox()` |
| `general/timeout` | `getTimeout()` |
| `general/external_order_id` | `getExternalOrderId()` |
| `general/application_id` | `getApplicationIdOverride()` |
| `general/available_carriers` | `getAvailableCarriers()` |
| `logging/failed_requests`, `logging/methods` | `isLoggingEnabled()`, `getMethodsToLog()` |
| `logging/personal_data`, `logging/files`, `logging/files_directory` | `isPersonalDataLoggingEnabled()`, `isFileLoggingEnabled()`, `getLogFilesDirectory()` |
| `sync/enabled`, `sync/status_enabled` | `isSyncEnabled()`, `isStatusSyncEnabled()` |
| `sync/status_schedule` | `getStatusIntervalMinutes()` |
| `sync/webhook_secret` | `getWebhookSecret()` |

[Shipments](shipments.md#reconciliation) describes the installation ID and the external order ID
sent with every order, and [Logging](logging.md) the log settings.

## Store settings

Every other getter takes an optional store id (null for the current store) and reads at store
scope. The admin shows each setting down to the scope in the table.

| Path (after `carriers/polkurier/`) | Getter | Scope |
|---|---|---|
| `active` | `isActive()` | website |
| `title` | `getTitle()` | store view |
| `checkout/tier_cart_value` | `getTierCartValue()` | website |
| `shipment_defaults/default_shipment_type` | `getDefaultShipmentType()` | website |
| `sender/use_store_information_address`, `sender/use_store_origin_address`, `sender/default_sender_email` | `shouldUseStoreInformationAddress()`, `shouldUseStoreOriginAddress()`, `getSenderEmailIdentity()` | website |
| `shipment_defaults/default_additional_services` | `getDefaultServices()` | website |
| `shipment_defaults/insure_by_default` | `shouldInsureByDefault()` | website |
| `shipment_defaults/default_cover_address` | `getDefaultCoverAddressId()` | website |
| `packing/parcel_weight` | `getParcelWeight()` | website |
| `packing/pack_new_shipments` | `isPackingNewShipments()` | website |
| `packing/packing_margin` | `getPackingMargin()` | website |
| `packing/weight_limit` | `getWeightLimit()` | website |
| `cod/cod_payment_methods` | `getCodPaymentMethods()` | website |
| `cod/default_cod_type`, `cod/default_cod_refund_type` | `getDefaultCodType()`, `getDefaultCodReturnType()` | website |
| `shipment_defaults/description_format` | `getDescriptionFormat()` | store view |
| `map/default_latitude`, `map/default_longitude`, `map/default_zoom` | `getMapLatitude()` … | store view |
| `map/show_list`, `map/show_search_input` | `shouldShowMapList()`, `shouldShowMapSearch()` | store view |
| `map/required_point_functions` | `getMapPointFunctions()` | website |

Magento's standard carrier settings (`sallowspecific`, `specificcountry`, `showmethod`,
`specificerrmsg`, `sort_order`) are read by Magento itself.

Getters that return choices return enums, the SDK's (`getDefaultShipmentType()`,
`getDefaultCodType()`, `getDefaultCodReturnType()` and so on) or the module's own in
[`Enum\Config`](../../src/Enum/Config) (`getTierCartValue()`, `getExternalOrderId()`,
`getParcelWeight()`, `getWeightLimit()`), so you never compare raw strings.

## Secrets

The customer ID, the token and the status webhook's secret use Magento's encrypted backend model and
are marked sensitive in `etc/di.xml`, so `app:config:dump` never writes them to
`app/etc/config.php`. [`ConfigProvider::getLogin()`](../../src/Model/Config/ConfigProvider.php),
`getToken()` and `getWebhookSecret()` decrypt them. Nothing else in the module reads those paths.

You can set them from the command line or in `env.php` like any sensitive setting:

```bash
bin/magento config:set --lock-env carriers/polkurier/general/customer_id 12345
```

## Map hosts

The map hosts, `maps.polkurier.pl` and `maps-sandbox.polkurier.pl`, are whitelisted for scripts,
styles, connections and images in `etc/csp_whitelist.xml`; `www.polkurier.pl` and the map tiles
(`tile.openstreetmap.org`, `*.tile.openstreetmap.org`) for images. A Content Security Policy the web
server sends on its own, outside Magento, has to allow them too, or the pickup point map doesn't
open or shows no tiles.

Next: [Database](database.md)
