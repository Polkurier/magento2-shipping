<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\AbstractDelete;
use Polkurier\Shipping\Model\Address\AddressHandler;

/**
 * Deletes a sender address, here and, with the next synchronization, in Polkurier.
 */
class Delete extends AbstractDelete
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::addresses';

    public function __construct(
        Context $context,
        AddressHandler $addressHandler,
    ) {
        parent::__construct($context, $addressHandler);
    }
}
