<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Shipment\Create;

use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;

class BaseForm extends \Magento\Backend\Block\System\Cache\Form
{
    public function __construct(
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        \Magento\Framework\Data\Form\FormKey $formKey,
        $data = []
    ) {
        parent::__construct($factoryElement, $factoryCollection, $formKey, $data);
    }


}
