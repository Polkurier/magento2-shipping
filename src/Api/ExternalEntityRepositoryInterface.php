<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api;

interface ExternalEntityRepositoryInterface
{
    /**
     * @param string $extId
     *
     * @return mixed
     */
    public function findOneByExtId(string $extId): mixed;
}
