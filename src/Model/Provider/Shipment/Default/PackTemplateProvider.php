<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider\Shipment\Default;

use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;

readonly class PackTemplateProvider
{
    /**
     * @param PackTemplateRepositoryInterface $packTemplateRepository
     */
    public function __construct(
        /**
         * @var PackTemplateRepositoryInterface
         */
        private PackTemplateRepositoryInterface $packTemplateRepository
    ) {}

    /**
     * @return int|null
     */
    public function getDefaultPackTemplateId(): ?int
    {
        return $this->packTemplateRepository->findOneDefault()?->getTemplateId();
    }
}
