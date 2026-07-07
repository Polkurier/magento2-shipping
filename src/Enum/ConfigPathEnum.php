<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

enum ConfigPathEnum: string
{
    case ACTIVE = 'carriers/polkurier/active';
    case GENERAL_SANDBOX_MODE = 'carriers/polkurier/general/sandbox_mode';
    case GENERAL_CUSTOMER_ID = 'carriers/polkurier/general/customer_id';
    case GENERAL_API_TOKEN = 'carriers/polkurier/general/api_token';
    case SHIPMENTS_HOME_COUNTRY_ID = 'carriers/polkurier/shipments/home_country_id';
    case SHIPMENTS_DEFAULT_CARRIER = 'carriers/polkurier/shipments/default_carrier';
    case SHIPMENTS_AVAILABLE_CARRIERS = 'carriers/polkurier/shipments/available_carriers';
    case SHIPMENTS_USE_STORE_INFORMATION_ADDRESS = 'carriers/polkurier/shipments/use_store_information_address';
    case SHIPMENTS_USE_STORE_ORIGIN_ADDRESS = 'carriers/polkurier/shipments/use_store_origin_address';
    case SHIPMENTS_DEFAULT_SENDER_EMAIL = 'carriers/polkurier/shipments/default_sender_email';
    case SHIPMENTS_DEFAULT_ADDITIONAL_SERVICES = 'carriers/polkurier/shipments/default_additional_services';
    case SHIPMENTS_DEFAULT_COD_TYPE = 'carriers/polkurier/shipments/default_cod_type';
    case SHIPMENTS_DEFAULT_COD_REFUND_TYPE = 'carriers/polkurier/shipments/default_cod_refund_type';
    case MAP_DEFAULT_LATITUDE = 'carriers/polkurier/map/default_latitude';
    case MAP_DEFAULT_LONGITUDE = 'carriers/polkurier/map/default_longitude';
    case MAP_DEFAULT_ZOOM = 'carriers/polkurier/map/default_zoom';
    case MAP_SHOW_LIST = 'carriers/polkurier/map/show_list';
    case MAP_SHOW_SEARCH_INPUT = 'carriers/polkurier/map/show_search_input';
    case MAP_REQUIRED_POINT_FUNCTIONS = 'carriers/polkurier/map/required_point_functions';
}
