<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * The order's pickup point: the one the customer chose in checkout or, when the Polkurier order
 * went with a different point, that point. The customer's choice is then kept in
 * {@see \Polkurier\Shipping\Api\Data\ShippingChoiceInterface}.
 *
 * @api
 */
interface OrderPointInterface
{
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_METHOD_CODE = 'method_code';
    public const string FIELD_POINT_ID = 'point_id';
    public const string FIELD_POINT_LABEL = 'point_label';
    public const string FIELD_POINT_COD = 'point_cod';

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function setOrderId(int $orderId): static;

    /**
     * @return string
     */
    public function getMethodCode(): string;

    /**
     * @param string $methodCode
     *
     * @return $this
     */
    public function setMethodCode(string $methodCode): static;

    /**
     * @return string
     */
    public function getPointId(): string;

    /**
     * @param string $pointId
     *
     * @return $this
     */
    public function setPointId(string $pointId): static;

    /**
     * @return string|null
     */
    public function getPointLabel(): ?string;

    /**
     * @param string|null $pointLabel
     *
     * @return $this
     */
    public function setPointLabel(?string $pointLabel): static;

    /**
     * Whether the point accepts cash on delivery. Null when that isn't known, e.g. when Polkurier
     * couldn't be asked; the point is then assumed to accept it.
     *
     * @return bool|null
     */
    public function getPointCod(): ?bool;

    /**
     * @param bool|null $pointCod null when not known
     *
     * @return $this
     */
    public function setPointCod(?bool $pointCod): static;
}
