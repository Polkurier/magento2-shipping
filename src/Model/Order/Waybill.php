<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Model\ResourceModel\Order\Waybill as WaybillResource;

class Waybill extends AbstractModel
{
    public const string FIELD_WAYBILL_ID = 'waybill_id';
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_NUMBER = 'number';
    public const string FIELD_DEFAULT = 'default';

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(WaybillResource::class);
    }
}
