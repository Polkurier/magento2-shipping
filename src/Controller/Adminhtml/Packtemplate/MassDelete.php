<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractMassDelete;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory;

class MassDelete extends AbstractMassDelete
{
    public function __construct(
        Context $context,
        PackTemplateHandler $handler,
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
