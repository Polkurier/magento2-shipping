<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Controller\Adminhtml\MasterData\AbstractEdit;
use Polkurier\Shipping\Model\Admin\AddressHandler;

/**
 * The form of a new or existing sender address.
 */
class Edit extends AbstractEdit
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::addresses';

    public function __construct(Context $context, AddressHandler $handler)
    {
        parent::__construct($context, $handler);
    }
}
