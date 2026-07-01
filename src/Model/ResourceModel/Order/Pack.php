<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Polkurier\Shipping\Api\Data\Order\PackInterface;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Api\Data\OrderInterfaceFactory;
use Polkurier\Shipping\Model\ResourceModel\Order as OrderResource;

class Pack extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_order_pack';
    public const string ID_FIELD = 'pack_id';

    /**
     * @param OrderResource $orderResource
     * @param OrderInterfaceFactory $orderFactory
     * @param Context $context
     * @param string|null $connectionName
     */
    public function __construct(
        /**
         * @var OrderResource
         */
        private readonly OrderResource $orderResource,

        /**
         * @var OrderInterfaceFactory
         */
        private readonly OrderInterfaceFactory $orderFactory,

        Context $context,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(static::TABLE_NAME, static::ID_FIELD);
    }

    /**
     * @param PackInterface $pack
     *
     * @return OrderInterface
     */
    public function loadOrder(PackInterface $pack): OrderInterface
    {
        $order = $this->orderFactory->create();
        $this->orderResource->load($order, $pack->getOrderId());

        return $order;
    }
}
