<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\Quote\ShipmentDataInterface;
use Polkurier\Shipping\Model\ResourceModel\Quote\ShipmentData as ShipmentDataResource;

class ShipmentData extends AbstractModel implements ShipmentDataInterface
{
    public const string FIELD_QUOTE_ID = 'quote_id';
    public const string FIELD_CARRIER_CODE = 'carrier_codee';
    public const string FIELD_POINT_ID = 'point_id';

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(ShipmentDataResource::class);
    }

    /**
     * @return int
     */
    public function getQuoteId(): int
    {
        return (int)$this->getData(static::FIELD_QUOTE_ID);
    }

    /**
     * @param int $quoteId
     *
     * @return void
     */
    public function setQuoteId(int $quoteId): void
    {
        $this->setData(static::FIELD_QUOTE_ID, $quoteId);
    }

    /**
     * @return string
     */
    public function getCarrierCode(): string
    {
        return (string)$this->getData(static::FIELD_CARRIER_CODE);
    }

    /**
     * @param string $carrierCode
     *
     * @return void
     */
    public function setCarrierCode(string $carrierCode): void
    {
        $this->setData(static::FIELD_CARRIER_CODE, $carrierCode);
    }

    /**
     * @return string|null
     */
    public function getPointId(): ?string
    {
        return $this->getData(static::FIELD_POINT_ID);
    }

    /**
     * @param string|null $pointId
     *
     * @return void
     */
    public function setPointId(?string $pointId): void
    {
        $this->setData(static::FIELD_POINT_ID, $pointId);
    }
}
