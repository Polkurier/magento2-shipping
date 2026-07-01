<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

/**
 * @method mixed getData(string $key = '', string|int|null $index = null)
 * @method $this setData(string|array|null $key, mixed $value = null)
 */
trait ExternalEntityTrait
{
    public const string FIELD_EXT_ID = 'ext_id';
    public const string FIELD_CREATED_AT = 'created_at';
    public const string FIELD_UPDATED_AT = 'updated_at';

    /**
     * @return string|null
     */
    public function getExtId(): ?string
    {
        return $this->getData(static::FIELD_EXT_ID);
    }

    /**
     * @param string|null $extId
     *
     * @return void
     */
    public function setExtId(?string $extId): void
    {
        $this->setData(static::FIELD_EXT_ID, $extId);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_CREATED_AT);
    }

    /**
     * @param \DateTimeInterface $createdAt
     *
     * @return void
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->setData(static::FIELD_CREATED_AT, $createdAt);
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->getData(static::FIELD_UPDATED_AT);
    }

    /**
     * @param \DateTimeInterface $updatedAt
     *
     * @return void
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->setData(static::FIELD_UPDATED_AT, $updatedAt);
    }
}
