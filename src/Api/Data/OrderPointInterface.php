<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * The pickup point a customer chose in checkout, carried onto the order.
 */
interface OrderPointInterface
{
    public const string ORDER_ID = 'order_id';
    public const string METHOD_CODE = 'method_code';
    public const string POINT_ID = 'point_id';
    public const string POINT_LABEL = 'point_label';
    public const string POINT_COD = 'point_cod';

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @param int $orderId
     * @return $this
     */
    public function setOrderId(int $orderId): static;

    /**
     * @return string
     */
    public function getMethodCode(): string;

    /**
     * @param string $methodCode
     * @return $this
     */
    public function setMethodCode(string $methodCode): static;

    /**
     * @return string
     */
    public function getPointId(): string;

    /**
     * @param string $pointId
     * @return $this
     */
    public function setPointId(string $pointId): static;

    /**
     * @return string|null
     */
    public function getPointLabel(): ?string;

    /**
     * @param string|null $pointLabel
     * @return $this
     */
    public function setPointLabel(?string $pointLabel): static;

    /**
     * @return bool
     */
    public function isPointCod(): bool;

    /**
     * @param bool $pointCod
     * @return $this
     */
    public function setPointCod(bool $pointCod): static;
}
