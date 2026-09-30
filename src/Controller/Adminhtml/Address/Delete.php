<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Magento\Backend\App\Action\Context;

use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractDelete;
use Polkurier\Shipping\Model\Admin\AddressHandler;

class Delete extends AbstractDelete
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::addresses';

    public function __construct(Context $context, AddressHandler $handler)
    {
        parent::__construct($context, $handler);
    }
}
