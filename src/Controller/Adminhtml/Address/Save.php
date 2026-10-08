<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractSave;
use Polkurier\Shipping\Model\Address\AddressHandler;

/**
 * Saves a sender address from its form.
 */
class Save extends AbstractSave
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::addresses';

    public function __construct(
        Context $context,
        AddressHandler $addressHandler,
        DataPersistorInterface $dataPersistor,
    ) {
        parent::__construct($context, $addressHandler, $dataPersistor);
    }
}
