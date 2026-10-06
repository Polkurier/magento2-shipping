<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

/**
 * The shipping the customer chose, kept when the order's shipping was changed to the carrier and
 * pickup point its Polkurier order went with. The order then shows what was actually sent.
 *
 * @api
 */
interface ShippingChoiceInterface
{
    public const string ORDER_ID = 'order_id';
    public const string SHIPPING_METHOD = 'shipping_method';
    public const string SHIPPING_DESCRIPTION = 'shipping_description';
    public const string POINT_METHOD_CODE = 'point_method_code';
    public const string POINT_ID = 'point_id';
    public const string POINT_LABEL = 'point_label';
    public const string POINT_COD = 'point_cod';
    public const string CREATED_AT = 'created_at';

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * The order's shipping method as the customer chose it, e.g. "polkurier_DPD".
     *
     * @return string|null
     */
    public function getShippingMethod(): ?string;

    /**
     * @return string|null
     */
    public function getShippingDescription(): ?string;

    /**
     * @return string|null the method code of the chosen pickup point, null without a point
     */
    public function getPointMethodCode(): ?string;

    /**
     * @return string|null
     */
    public function getPointId(): ?string;

    /**
     * @return string|null
     */
    public function getPointLabel(): ?string;

    /**
     * @return bool
     */
    public function isPointCod(): bool;

    /**
     * @return string|null when the order's shipping was first changed, UTC
     */
    public function getCreatedAt(): ?string;
}
