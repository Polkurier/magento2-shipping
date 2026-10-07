<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Api\Data\OrderPointInterface;

/**
 * The pickup point chosen for an order.
 */
class OrderPoint extends AbstractModel implements OrderPointInterface
{
    public const string ID_FIELD = self::FIELD_ORDER_ID;

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_order_point';

    /**
     * @var string
     */
    protected $_eventObject = 'orderPoint';

    public function getOrderId(): int
    {
        $value = $this->getData(self::FIELD_ORDER_ID);

        return (int)$value;
    }

    public function setOrderId(int $orderId): static
    {
        return $this->setData(self::FIELD_ORDER_ID, $orderId);
    }

    public function getMethodCode(): string
    {
        $value = $this->getData(self::FIELD_METHOD_CODE);

        return (string)$value;
    }

    public function setMethodCode(string $methodCode): static
    {
        return $this->setData(self::FIELD_METHOD_CODE, $methodCode);
    }

    public function getPointId(): string
    {
        $value = $this->getData(self::FIELD_POINT_ID);

        return (string)$value;
    }

    public function setPointId(string $pointId): static
    {
        return $this->setData(self::FIELD_POINT_ID, $pointId);
    }

    public function getPointLabel(): ?string
    {
        $value = $this->getData(self::FIELD_POINT_LABEL);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPointLabel(?string $pointLabel): static
    {
        return $this->setData(self::FIELD_POINT_LABEL, $pointLabel);
    }

    public function getPointCod(): ?bool
    {
        $value = $this->getData(self::FIELD_POINT_COD);

        return $value === null || $value === '' ? null : (bool)$value;
    }

    public function setPointCod(?bool $pointCod): static
    {
        return $this->setData(self::FIELD_POINT_COD, $pointCod === null ? null : (int)$pointCod);
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\OrderPoint::class);
    }
}
