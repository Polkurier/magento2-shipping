<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

interface ExternalEntityInterface
{
    /**
     * @return string|null
     */
    public function getExtId(): ?string;

    /**
     * @param string|null $extId
     *
     * @return void
     */
    public function setExtId(?string $extId): void;

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface;

    /**
     * @param \DateTimeInterface $createdAt
     *
     * @return void
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): void;

    /**
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface;

    /**
     * @param \DateTimeInterface $updatedAt
     *
     * @return void
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): void;
}
