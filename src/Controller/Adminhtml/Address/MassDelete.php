<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractMassDelete;
use Polkurier\Shipping\Model\Admin\AddressHandler;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory;

/**
 * Deletes the sender addresses selected in their grid.
 */
class MassDelete extends AbstractMassDelete
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::addresses';

    public function __construct(
        Context $context,
        AddressHandler $handler,
        Filter $filter,
        private readonly CollectionFactory $collectionFactory,
    ) {
        parent::__construct($context, $handler, $filter);
    }

    protected function createCollection(): AbstractDb
    {
        return $this->collectionFactory->create();
    }
}
