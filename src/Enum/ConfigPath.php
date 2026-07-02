<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

enum ConfigPath: string
{
    case ENABLED = 'carriers/polkurier/active';
    case SANDBOX_MODE = 'carriers/polkurier/sandbox_mode';
    case CUSTOMER_ID = 'carriers/polkurier/customer_id';
    case API_TOKEN = 'carriers/polkurier/api_token';
    case HOME_COUNTRY_ID = 'carriers/polkurier/home_country_id';
    case AVAILABLE_CARRIERS = 'carriers/polkurier/available_carriers';
    case MAP_DEFAULT_LATITUDE = 'carriers/polkurier/map/default_latitude';
    case MAP_DEFAULT_LONGITUDE = 'carriers/polkurier/map/default_longitude';
    case MAP_DEFAULT_ZOOM = 'carriers/polkurier/map/default_zoom';
    case MAP_SHOW_LIST = 'carriers/polkurier/map/show_list';
    case MAP_SHOW_SEARCH_INPUT = 'carriers/polkurier/map/show_search_input';
    case MAP_REQUIRED_POINT_FUNCTIONS = 'carriers/polkurier/map/required_point_functions';
}
