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
    public const string ID_FIELD = 'order_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'polkurier_order_point';

    /**
     * @var string
     */
    protected $_eventObject = 'orderPoint';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\OrderPoint::class);
    }

    public function getOrderId(): int
    {
        $value = $this->getData(self::ORDER_ID);

        return (int)$value;
    }

    public function setOrderId(int $orderId): static
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getMethodCode(): string
    {
        $value = $this->getData(self::METHOD_CODE);

        return (string)$value;
    }

    public function setMethodCode(string $methodCode): static
    {
        return $this->setData(self::METHOD_CODE, $methodCode);
    }

    public function getPointId(): string
    {
        $value = $this->getData(self::POINT_ID);

        return (string)$value;
    }

    public function setPointId(string $pointId): static
    {
        return $this->setData(self::POINT_ID, $pointId);
    }

    public function getPointLabel(): ?string
    {
        $value = $this->getData(self::POINT_LABEL);

        return $value === null || $value === '' ? null : (string)$value;
    }

    public function setPointLabel(?string $pointLabel): static
    {
        return $this->setData(self::POINT_LABEL, $pointLabel);
    }

    public function isPointCod(): bool
    {
        $value = $this->getData(self::POINT_COD);

        return (bool)$value;
    }

    public function setPointCod(bool $pointCod): static
    {
        return $this->setData(self::POINT_COD, (int)$pointCod);
    }
}
