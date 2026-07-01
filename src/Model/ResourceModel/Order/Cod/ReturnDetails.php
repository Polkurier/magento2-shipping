<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Order\Cod;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Polkurier\Shipping\Api\Data\Order\Cod\ReturnDetailsInterface;
use Polkurier\Shipping\Api\Data\Order\CodInterface;
use Polkurier\Shipping\Api\Data\Order\CodInterfaceFactory;
use Polkurier\Shipping\Model\ResourceModel\Order\Cod as CodResource;

class ReturnDetails extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_order_cod_return_details';
    public const string ID_FIELD = 'item_id';

    /**
     * @param CodResource $codResource
     * @param CodInterfaceFactory $codFactory
     * @param Context $context
     * @param string|null $connectionName
     */
    public function __construct(
        private readonly CodResource $codResource,
        private readonly CodInterfaceFactory $codFactory,
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
     * @param ReturnDetailsInterface $returnDetails
     *
     * @return CodInterface
     */
    public function loadParent(ReturnDetailsInterface $returnDetails): CodInterface
    {
        $cod = $this->codFactory->create();
        $this->codResource->load($cod, $returnDetails->getOrderId());

        return $cod;
    }
}
