<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider\Ext;

use Polkurier\Webservice\Api\GeoServiceInterface;

/**
 * TODO: cache map token
 */
readonly class MapTokenProvider
{
    /**
     * @param GeoServiceInterface $geoService
     */
    public function __construct(
        /**
         * @var GeoServiceInterface
         */
        private GeoServiceInterface $geoService,
    ) {}

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->geoService->getMapToken()->getParsedResponse()->getResponse()->getToken();
    }
}
