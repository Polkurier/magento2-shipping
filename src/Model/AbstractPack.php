<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;
use Polkurier\Webservice\Enum\PackTypeEnum;

abstract class AbstractPack extends AbstractModel
{
    public const string FIELD_TYPE = 'type';
    public const string FIELD_LENGTH = 'length';
    public const string FIELD_HEIGHT = 'height';
    public const string FIELD_WIDTH = 'width';
    public const string FIELD_WEIGHT = 'weight';

    /**
     * @return PackTypeEnum
     */
    public function getType(): PackTypeEnum
    {
        return PackTypeEnum::from($this->getData(static::FIELD_TYPE));
    }

    /**
     * @param PackTypeEnum $type
     *
     * @return void
     */
    public function setType(PackTypeEnum $type): void
    {
        $this->setData(static::FIELD_TYPE, $type->value);
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return (int)$this->getData(static::FIELD_LENGTH);
    }

    /**
     * @param int $length
     *
     * @return void
     */
    public function setLength(int $length): void
    {
        $this->setData(static::FIELD_LENGTH, $length);
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return (int)$this->getData(static::FIELD_WIDTH);
    }

    /**
     * @param int $width
     *
     * @return void
     */
    public function setWidth(int $width): void
    {
        $this->setData(static::FIELD_WIDTH, $width);
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return (int)$this->getData(static::FIELD_HEIGHT);
    }

    /**
     * @param int $height
     *
     * @return void
     */
    public function setHeight(int $height): void
    {
        $this->setData(static::FIELD_HEIGHT, $height);
    }

    /**
     * @return float
     */
    public function getWeight(): float
    {
        return (float)$this->getData(static::FIELD_WEIGHT);
    }

    /**
     * @param float $weight
     *
     * @return void
     */
    public function setWeight(float $weight): void
    {
        $this->setData(static::FIELD_WEIGHT, $weight);
    }
}
