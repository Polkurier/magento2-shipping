<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Model\ResourceModel\Order\Item as ItemResource;

class Item extends AbstractModel
{
    public const string FIELD_ITEM_ID = 'item_id';
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_AMOUNT = 'amount';
    public const string FIELD_PRICE = 'price';
    public const string FIELD_VALUE_NET = 'value_net';
    public const string FIELD_VALUE_GROSS = 'value_gross';
    public const string FIELD_TYPE = 'type';

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(ItemResource::class);
    }
}
