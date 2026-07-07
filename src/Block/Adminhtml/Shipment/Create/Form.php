<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Shipment\Create;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Helper\Admin;
use Magento\Shipping\Block\Adminhtml\Create\Form as BaseForm;
use Magento\Tax\Helper\Data;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Api\OrderRepositoryInterface;

class Form extends BaseForm
{
    /**
     * @var OrderInterface|null
     */
    private ?OrderInterface $polkurierOrder = null;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param Context $context
     * @param Registry $registry
     * @param Admin $adminHelper
     * @param array $data
     * @param Data|null $taxHelper
     */
    public function __construct(
        /**
         * @var OrderRepositoryInterface
         */
        private readonly OrderRepositoryInterface $orderRepository,

        Context $context,
        Registry $registry,
        Admin $adminHelper,
        array $data = [],
        ?Data $taxHelper = null
    ) {
        parent::__construct($context, $registry, $adminHelper, $data, $taxHelper);
    }

    /**
     * @return OrderInterface
     */
    public function getPolkurierOrder(): OrderInterface
    {
        if (!$this->polkurierOrder === null) {
            $this->polkurierOrder = $this->orderRepository->findOneByOrderId((int)$this->getOrder()->getId());
        }

        return $this->polkurierOrder;
    }
}
