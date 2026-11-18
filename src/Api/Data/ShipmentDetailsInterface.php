<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * The keys of a shipment's local copy of its Polkurier order
 * ({@see \Polkurier\Shipping\Api\Data\ShipmentInterface::getDetails()}) and of the carrier's scans
 * ({@see \Polkurier\Shipping\Api\Data\ShipmentInterface::getTracking()}). The module's own names,
 * not the API's.
 *
 * Under the order's keys: FIELD_SENDER, FIELD_RECIPIENT and FIELD_COVER_ADDRESS hold FIELD_ADDRESS_*
 * keys; FIELD_COD holds FIELD_COD_*; FIELD_PICKUP holds FIELD_PICKUP_*; FIELD_WAYBILLS, FIELD_PACKS
 * and FIELD_ITEMS are lists of FIELD_WAYBILL_*, FIELD_PACK_* and FIELD_ITEM_* entries. Each scan
 * holds FIELD_SCAN_* keys. Dates and times are in Polish time, as Polkurier reports them
 * ({@see \Polkurier\Shipping\Model\Sdk\PolkurierTimeZoneProvider::ZONE}).
 *
 * @api
 */
interface ShipmentDetailsInterface
{
    public const string FIELD_NUMBER = 'number';
    public const string FIELD_DATE = 'date';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_CARRIER = 'carrier';
    public const string FIELD_CARRIER_NAME = 'carrier_name';
    public const string FIELD_SHIPMENT_TYPE = 'shipment_type';
    public const string FIELD_STATUS = 'status';
    public const string FIELD_STATUS_CODE = 'status_code';
    public const string FIELD_STATUS_DATE = 'status_date';
    public const string FIELD_DELIVERED_DATE = 'delivered_date';
    public const string FIELD_TRACKING_URL = 'tracking_url';
    public const string FIELD_WAYBILLS = 'waybills';
    public const string FIELD_SENDER = 'sender';
    public const string FIELD_RECIPIENT = 'recipient';
    public const string FIELD_COVER_ADDRESS = 'cover_address';
    public const string FIELD_COD = 'cod';
    public const string FIELD_INSURANCE = 'insurance';
    public const string FIELD_PICKUP = 'pickup';
    public const string FIELD_SERVICES = 'services';
    public const string FIELD_ADDITIONAL_FIELDS = 'additional_fields';
    public const string FIELD_PACKS = 'packs';
    public const string FIELD_ITEMS = 'items';
    public const string FIELD_PRICE_NET = 'price_net';
    public const string FIELD_PRICE_GROSS = 'price_gross';
    public const string FIELD_REBATE_NET = 'rebate_net';
    public const string FIELD_REBATE_GROSS = 'rebate_gross';
    public const string FIELD_VAT_RATE = 'vat_rate';
    public const string FIELD_IS_PAID = 'is_paid';
    public const string FIELD_HAS_INVOICE = 'has_invoice';
    public const string FIELD_UNPAID_AMOUNT = 'unpaid_amount';

    public const string FIELD_WAYBILL_NUMBER = 'number';
    public const string FIELD_WAYBILL_IS_DEFAULT = 'is_default';

    public const string FIELD_ADDRESS_PERSON = 'person';
    public const string FIELD_ADDRESS_COMPANY = 'company';
    public const string FIELD_ADDRESS_STREET = 'street';
    public const string FIELD_ADDRESS_POSTCODE = 'postcode';
    public const string FIELD_ADDRESS_CITY = 'city';
    public const string FIELD_ADDRESS_PROVINCE = 'province';
    public const string FIELD_ADDRESS_COUNTRY = 'country';
    public const string FIELD_ADDRESS_PHONE = 'phone';
    public const string FIELD_ADDRESS_EMAIL = 'email';
    public const string FIELD_ADDRESS_POINT_ID = 'point_id';
    public const string FIELD_ADDRESS_POINT_LABEL = 'point_label';

    public const string FIELD_COD_AMOUNT = 'amount';
    public const string FIELD_COD_TYPE = 'type';
    public const string FIELD_COD_RETURN_TYPE = 'return_type';
    public const string FIELD_COD_BANK_ACCOUNT = 'bank_account';
    public const string FIELD_COD_STATUS = 'status';

    public const string FIELD_PICKUP_DATE = 'date';
    public const string FIELD_PICKUP_TIME_FROM = 'time_from';
    public const string FIELD_PICKUP_TIME_TO = 'time_to';
    public const string FIELD_PICKUP_NO_COURIER_ORDER = 'no_courier_order';
    public const string FIELD_PICKUP_MULTI_PICKUP = 'multi_pickup';
    public const string FIELD_PICKUP_NUMBER = 'pickup_number';

    public const string FIELD_PACK_TYPE = 'type';
    public const string FIELD_PACK_LENGTH = 'length';
    public const string FIELD_PACK_WIDTH = 'width';
    public const string FIELD_PACK_HEIGHT = 'height';
    public const string FIELD_PACK_WEIGHT = 'weight';
    public const string FIELD_PACK_AMOUNT = 'amount';
    public const string FIELD_PACK_PRICE_NET = 'price_net';
    public const string FIELD_PACK_PRICE_GROSS = 'price_gross';

    public const string FIELD_ITEM_DESCRIPTION = 'description';
    public const string FIELD_ITEM_TYPE = 'type';
    public const string FIELD_ITEM_AMOUNT = 'amount';
    public const string FIELD_ITEM_VALUE_NET = 'value_net';
    public const string FIELD_ITEM_VALUE_GROSS = 'value_gross';

    public const string FIELD_SCAN_DATE = 'date';
    public const string FIELD_SCAN_STATUS_CODE = 'status_code';
    public const string FIELD_SCAN_STATUS = 'status';
    public const string FIELD_SCAN_LOCATION = 'location';
    public const string FIELD_SCAN_DESCRIPTION = 'description';
}
