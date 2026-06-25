<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Model\ResourceModel\Order\Pack as PackResource;

class Pack extends AbstractModel
{
    public const string FIELD_PACK_ID = 'pack_id';
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_TYPE = 'type';
    public const string FIELD_LENGTH = 'length';
    public const string FIELD_HEIGHT = 'height';
    public const string FIELD_WIDTH = 'width';
    public const string FIELD_WEIGHT = 'weight';
    public const string FIELD_AMOUNT = 'amount';
    public const string FIELD_COD = 'cod';
    public const string FIELD_INSURANCE = 'insurance';
    public const string FIELD_PRICE_NET = 'price_net';
    public const string FIELD_PRICE_GROSS = 'price_gross';

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(PackResource::class);
    }
}
