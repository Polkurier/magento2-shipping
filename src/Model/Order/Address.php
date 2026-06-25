<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Polkurier\Shipping\Model\ResourceModel\Order\Address as AddressResource;

class Address extends AbstractModel
{
    public const string FIELD_ADDRESS_ID = 'address_id';
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_TYPE = 'type';
    public const string FIELD_PERSON = 'person';
    public const string FIELD_COMPANY = 'company';
    public const string FIELD_STREET = 'street';
    public const string FIELD_CITY = 'city';
    public const string FIELD_POSTCODE = 'postcode';
    public const string FIELD_COUNTRY = 'country';
    public const string FIELD_POINT_ID = 'point_id';
    public const string FIELD_EMAIL = 'email';
    public const string FIELD_PHONE = 'phone';

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(AddressResource::class);
    }
}
