<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Polkurier\Webservice\Api\Data\Entity\BankAccountInterface as BaseBankAccountInterface;

interface BankAccountInterface extends BaseBankAccountInterface
{
    /**
     * @return int|null
     */
    public function getAccountId(): ?int;

    /**
     * @param int|null $accountId
     *
     * @return void
     */
    public function setAccountId(?int $accountId): void;
}
