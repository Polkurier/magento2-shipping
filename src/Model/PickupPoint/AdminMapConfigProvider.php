<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PickupPoint;

use Magento\Backend\Model\UrlInterface;
use Polkurier\Shipping\Model\Config\ConfigProvider;

/**
 * The pickup point map's settings for the admin pages that open it.
 */
readonly class AdminMapConfigProvider
{
    /**
     * The admin action a page asks for a fresh token.
     */
    public const string TOKEN_ROUTE = 'polkurier/shipment/mapToken';

    public function __construct(
        private ConfigProvider $configProvider,
        private UrlInterface $url,
        private MapTokenProvider $mapTokenProvider,
    ) {}

    /**
     * Where the map is and where a page asks for a fresh token; with $withToken, also the token and
     * when it runs out, empty and 0 when it can't be obtained.
     *
     * @return array{baseUrl: string, tokenUrl: string, token?: string, tokenExpires?: int}
     */
    public function getMapConfig(bool $withToken): array
    {
        $config = [
            'baseUrl' => $this->configProvider->getMapUrl() . '/',
            'tokenUrl' => $this->url->getUrl(self::TOKEN_ROUTE),
        ];
        if (!$withToken) {
            return $config;
        }
        $token = $this->mapTokenProvider->getTokenWithExpiry();

        return $config + ['token' => $token['token'], 'tokenExpires' => $token['expires']];
    }
}
