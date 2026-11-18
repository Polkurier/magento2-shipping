<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Polkurier\Webservice\Api\SerializerConfigInterface;
use Polkurier\Webservice\Api\SerializerInterface;
use Polkurier\Webservice\PolkurierWebservice;

/**
 * The SDK's payload serializer, for tests that need a payload as the SDK writes it.
 */
final class SdkSerializer
{
    public static function create(?SerializerConfigInterface $config = null): SerializerInterface
    {
        return (new PolkurierWebservice(SdkConfig::create(), serializerConfig: $config))->serializer();
    }
}
