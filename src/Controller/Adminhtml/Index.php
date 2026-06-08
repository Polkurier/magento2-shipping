<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

abstract class Index extends Action
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::root';

    /**
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        /**
         * @var PageFactory
         */
        protected readonly PageFactory $resultPageFactory,

        /**
         * @var ForwardFactory
         */
        protected readonly ForwardFactory $resultForwardFactory,

        /**
         * @var JsonFactory
         */
        protected readonly JsonFactory $resultJsonFactory,

        /**
         * @var LoggerInterface
         */
        protected readonly LoggerInterface $logger,

        Context $context
    ) {
        parent::__construct($context);
    }
}
