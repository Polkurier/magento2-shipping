<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Validator;

use Polkurier\Shipping\Model\Provider\ConfigProvider;
use Polkurier\Webservice\Api\UtilsServiceInterface;

readonly class CredentialsValidator
{
    /**
     * @param UtilsServiceInterface $utilsService
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        /**
         * @var UtilsServiceInterface
         */
        private UtilsServiceInterface $utilsService,

        /**
         * @var ConfigProvider
         */
        private ConfigProvider $configProvider,
    ) {}

    /**
     * @return void
     */
    public function validate(): void
    {
        $this->utilsService->testAuthApi($this->configProvider->getConfig()->getAuthorization());
    }
}
