<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Carrier;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\CarrierConfig\CarrierSortOrderUpdater;

/**
 * Saves the order of the carriers' rows after one was dragged in the carriers grid.
 */
class Arrange extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly CarrierSortOrderUpdater $carrierSortOrderUpdater,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $this->carrierSortOrderUpdater->arrange(
            \array_values(\array_filter(\array_map('intval', (array)$this->getRequest()->getParam('ids', [])))),
        );

        return [];
    }
}
