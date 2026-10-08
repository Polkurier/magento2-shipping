<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Database

[Developer guide](README.md) › Database

All tables are declared in `etc/db_schema.xml`, with `db_schema_whitelist.json` next to it. Data
patches live in `Setup/Patch/Data`.

| Data patch | |
|---|---|
| [`AddProductDimensionsPatch`](../../src/Setup/Patch/Data/AddProductDimensionsPatch.php) | adds the product attributes for one unit's shipping size, used to pack orders into parcels |
| [`EnableCacheTypePatch`](../../src/Setup/Patch/Data/EnableCacheTypePatch.php) | switches the module's cache type on when the module is installed |

## Tables

| Table | Holds |
|---|---|
| `polkurier_carrier_config` | one row per carrier: on/off, default label, sort order, pricing |
| `polkurier_carrier_label` | carrier labels per store view |
| `polkurier_address` | sender addresses, with their sync state |
| `polkurier_bank_account` | bank accounts for cash on delivery, with their sync state |
| `polkurier_pack_template` | pack templates, with their sync state |
| `polkurier_quote_point` | the pickup point chosen for a quote address |
| `polkurier_order_point` | the pickup point of an order |
| `polkurier_order_shipping_choice` | the shipping the customer chose, kept while the order shows the carrier it actually went with |
| `polkurier_shipment` | Polkurier shipments of Magento orders |

Read and write them through the repositories (see [Service contracts](service-contracts.md)).
Carrier labels are loaded and saved with their carrier.
`polkurier_order_shipping_choice` is read through the order's `polkurier_shipping_choice` extension
attribute (see [The order's shipping](shipments.md#the-orders-shipping)).

## Carrier settings

`polkurier_carrier_config` is unique on `carrier_code`.

- `carrier_name` is the carrier's own name, refreshed from the account; `custom_label` is the
  merchant's.
- `logo` is the checkout logo, a path relative to the media directory (`shipping/carrier_logos/…`,
  see [`Model\Carrier\CarrierLogoStorage`](../../src/Model/Carrier/CarrierLogoStorage.php)).
- `available` says whether the account still offers the carrier. A carrier that disappears from
  the account keeps its row, with `available = 0`, so its settings survive; the carriers grid
  and checkout leave it out.
- `pricing_model` is one of
  [`Enum\CarrierConfig\PricingModelEnum`](../../src/Enum/CarrierConfig/PricingModelEnum.php);
  `price_info` holds the model's settings as JSON (a price, or a list of tiers).

## Synced entities

`polkurier_address`, `polkurier_bank_account` and `polkurier_pack_template` share a set of columns
([`Api\Data\SyncableInterface`](../../src/Api/Data/SyncableInterface.php)):

| Column | |
|---|---|
| `ext_id` | the entry's id on the account, unique; null until it has been created there |
| `sync_state` | in sync, waiting to be sent, error, or awaiting confirmation |
| `sync_error` | why the last push failed |
| `remote_hash` | a hash of the remote entry as of the last sync |
| `remote_updated_at` | when the remote side was last seen changing |
| `synced_at` | when the entry was last synced |
| `deleted_at` | set when the entry is deleted locally and the delete hasn't reached the account yet |

Rows with `deleted_at` set are hidden everywhere except in the sync. See
[Synchronization](synchronization.md).

## Pickup points

`polkurier_quote_point` has one row per `(quote_id, quote_address_id)`, with `quote_address_id = 0`
in the regular checkout, which has one shipping address. `polkurier_order_point` has one row per
order. Both keep the carrier method code, the point id, a label for display and whether the point
accepts cash on delivery (`point_cod`: 1 or 0, null when unknown).
`polkurier_order_shipping_choice` keeps the same data for the customer's choice.

## Shipments

`polkurier_shipment` has one row for each Polkurier order placed for a Magento order, whatever became
of it (`pending`, `unknown`, `created`, `cancelled`). An order can also have at most one `failed`
row: the shipment that couldn't be placed. The order's next attempt reuses that row instead of
adding one (see [Placing](shipments.md#placing)). A request refused as it stands leaves no row.

| Column | |
|---|---|
| `order_id`, `store_id` | the Magento order and its store |
| `shipment_id` | the Magento shipment the Polkurier order is linked to. Indexed, not unique: a Magento shipment keeps the rows of orders cancelled before the one on it. Null until the Magento shipment exists, or when creating it failed |
| `state` | `pending`, `created`, `unknown`, `failed` or `cancelled` (see [Shipments](shipments.md)) |
| `polkurier_number` | the order number on the account |
| `carrier_code`, `description`, `recipient_postcode`, `recipient_phone`, `pack_count` | what was sent, kept for matching during reconciliation |
| `waybills` | JSON list of the carrier's tracking numbers |
| `status_code`, `status_label`, `status_date`, `delivered_at`, `tracking_url` | the latest delivery status; its times in UTC |
| `external_order_id`, `reserved_shipment_number` | the shop's id sent with the order, and the shipment number reserved for a Magento shipment not yet created |
| `price_net`, `price_gross` | what Polkurier charged |
| `insurance_amount`, `cod_amount`, `cod_status` | insurance and cash on delivery |
| `multi_pickup`, `pickup_booked` | whether the order was placed for a collective pickup, and whether that pickup is booked |
| `is_final`, `status_checked_at` | whether the shipment is finished, and when its status was last checked |
| `attention_notified_at` | when the admin was told about this shipment needing attention (see [Shipments](shipments.md#shipments-that-need-attention)); cleared by a new attempt |
| `draft` | the shipment as it was filled in (JSON, see [The draft](shipments.md#the-draft)); sending it again starts from this |
| `request` | the serialized request as sent, without credentials |
| `details`, `details_synced_at` | the local copy of the order as Polkurier reports it (JSON), and when it was read |
| `ship_items` | the quantities the Magento shipment carries, by order item id (JSON); empty means everything left to ship |
| `ship_packages` | the packages of the Magento shipment, as Magento keeps them (JSON): each parcel and what it holds |
| `target_shipment_id` | when the order has nothing left to ship: the existing Magento shipment the Polkurier order is added to once placed |
| `tracking`, `tracking_synced_at` | the local copy of the carrier's scans, newest first (JSON), and when they were read |
| `last_error` | why the last action failed, or a warning such as probable duplicates |

The stored request, the order copy and the scans contain the recipient's personal data. If your
data retention policy requires it, clear them for old shipments; the details page reads the order
from Polkurier again when its copy is missing.

Next: [Checkout](checkout.md)
