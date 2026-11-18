<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Polkurier\Webservice\Config;
use Polkurier\Webservice\Dto\Request\AuthorizationMessage;

/**
 * The SDK settings of the account tests talk to.
 */
final class SdkConfig
{
    public const string TOKEN = 'test-token-secret';
    public const string LOGIN = '12345';

    public static function create(): Config
    {
        return new Config(
            authorization: new AuthorizationMessage(token: self::TOKEN, login: self::LOGIN),
            platform: 'magento2',
            platformVersion: '1.0.0',
            apiUrl: 'http://api.test',
        );
    }
}
