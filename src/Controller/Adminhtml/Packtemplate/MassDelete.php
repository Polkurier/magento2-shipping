<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Packtemplate;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Controller\Adminhtml\AbstractMassDelete;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateHandler;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory;

/**
 * Deletes the pack templates selected in their grid.
 */
class MassDelete extends AbstractMassDelete
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    public function __construct(
        Context $context,
        PackTemplateHandler $packTemplateHandler,
        Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $packTemplateHandler, $filter, $flashMessageCollectorFactory);
    }

    protected function createCollection(): AbstractDb
    {
        return $this->collectionFactory->create();
    }
}
