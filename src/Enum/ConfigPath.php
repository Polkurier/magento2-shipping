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
    case CARRIER_CONFIG = 'carriers/polkurier/carrier_config';
}
