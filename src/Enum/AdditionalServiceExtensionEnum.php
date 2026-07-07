<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

enum AdditionalServiceExtensionEnum: string
{
    case COD = 'COD';
    case INSURANCE = 'INSURANCE';
    case COVER_ADDRESS = 'COVER_ADDRESS';
}
