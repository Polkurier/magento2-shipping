<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Shipment;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Polkurier\Shipping\Model\PolkurierCarrier;

class Form extends Template
{
    /**
     * @param Registry $registry
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        /**
         * @var Registry
         */
        private readonly Registry $registry,

        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return OrderInterface|null
     */
    public function getOrder(): ?OrderInterface
    {
        return $this->registry->registry('current_shipment')?->getOrder();
    }

    /**
     * @return bool
     */
    public function isPolkurierCarrier(): bool
    {
        return $this->getOrder()?->getShippingMethod(true)?->getCarrierCode() === PolkurierCarrier::CODE;
    }
}
