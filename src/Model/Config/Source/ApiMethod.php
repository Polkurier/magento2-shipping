<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Http\ApiMethodEnum;

/**
 * API methods whose whole traffic may be logged.
 */
readonly class ApiMethod extends AbstractOptions
{
    public function toArray(): array
    {
        $methods = [];
        foreach (ApiMethodEnum::cases() as $method) {
            $methods[$method->value] = $method->value;
        }
        \ksort($methods);

        return $methods;
    }
}
