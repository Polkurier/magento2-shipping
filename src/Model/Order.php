<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Polkurier\Shipping\Model\ResourceModel\Order as OrderResource;
use Polkurier\Shipping\Model\ResourceModel\Order\Address as AddressResource;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod as CodResource;
use Polkurier\Shipping\Model\ResourceModel\Order\Item\CollectionFactory as ItemCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\Order\Pack\CollectionFactory as PackCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\Order\Waybill\CollectionFactory as WaybillCollectionFactory;

class Order extends AbstractModel
{
    public const string FIELD_ORDER_ID = 'order_id';
    public const string FIELD_PARENT_ORDER_ID = 'parent_order_id';
    public const string FIELD_PARENT_SHIPMENT_ID = 'parent_shipment_id';
    public const string FIELD_EXT_ID = 'ext_id';
    public const string FIELD_STATUS = 'status';
    public const string FIELD_STATUS_CODE = 'status_code';
    public const string FIELD_EXT_CREATED_AT = 'ext_created_at';
    public const string FIELD_STATUS_DATE = 'status_date';
    public const string FIELD_SHIPMENT_TYPE = 'shipment_type';
    public const string FIELD_CARRIER_CODE = 'carrier_code';
    public const string FIELD_DESCRIPTION = 'description';
    public const string FIELD_PRICE_NET = 'price_net';
    public const string FIELD_PRICE_GROSS = 'price_gross';
    public const string FIELD_UNPAID_AMOUNT = 'unpaid_amount';
    public const string FIELD_PAID = 'paid';
    public const string FIELD_INVOICE = 'invoice';
    public const string FIELD_INSURANCE = 'insurance';
    public const string FIELD_COURIER_SERVICE = 'courier_service';
    public const string FIELD_PICKUP_FROM = 'pickup_from';
    public const string FIELD_PICKUP_TO = 'pickup_to';
    public const string FIELD_PICKUP_NO_COURIER_ORDER = 'pickup_no_courier_order';
    public const string FIELD_TRACKING_URI = 'tracking_uri';
    public const string FIELD_DELIVERED_AT = 'delivered_at';
    public const string FIELD_ADDITIONAL_FIELDS = 'additional_fields';
    public const string FIELD_CREATED_AT = 'created_at';
    public const string FIELD_UPDATED_AT = 'updated_at';

    public function __construct(
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly PackCollectionFactory $packCollectionFactory,
        private readonly WaybillCollectionFactory $waybillCollectionFactory,
        private readonly AddressResource $addressResource,
        private readonly CodResource $codResource,
        Context $context,
        Registry $registry,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function _construct(): void
    {
        $this->_init(OrderResource::class);
    }
}
