<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Shipment\Create\Form;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Helper\Admin;
use Magento\Tax\Helper\Data;
use Polkurier\Shipping\Api\OrderRepositoryInterface;
use Polkurier\Shipping\Block\Adminhtml\Shipment\Create\Form;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;

class AdditionalServices extends Form
{
    /**
     * @param AdditionalService $additionalService
     * @param OrderRepositoryInterface $orderRepository
     * @param Context $context
     * @param Registry $registry
     * @param Admin $adminHelper
     * @param array $data
     * @param Data|null $taxHelper
     */
    public function __construct(
        /**
         * @var AdditionalService
         */
        private readonly AdditionalService $additionalService,

        OrderRepositoryInterface $orderRepository,
        Context $context,
        Registry $registry,
        Admin $adminHelper,
        array $data = [],
        ?Data $taxHelper = null
    ) {
        parent::__construct($orderRepository, $context, $registry, $adminHelper, $data, $taxHelper);
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->additionalService->toOptionArray();
    }
}
