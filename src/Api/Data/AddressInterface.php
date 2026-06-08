<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Polkurier\Webservice\Api\Data\Entity\AddressInterface as BaseAddressInterface;

interface AddressInterface extends BaseAddressInterface
{
    /**
     * @return int|null
     */
    public function getAddressId(): ?int;

    /**
     * @param int|null $id
     *
     * @return void
     */
    public function setAddressId(?int $id): void;
}
