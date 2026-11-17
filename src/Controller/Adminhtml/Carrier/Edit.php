<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Carrier;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;

/**
 * The settings form of one carrier.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Magento_Shipping::carriers';
    public const string ROUTE = 'polkurier/carrier/edit';

    public function __construct(
        Context $context,
        private readonly CarrierConfigRepositoryInterface $carrierConfigRepository,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        // Carriers come from Polkurier's list, so there is no new one to open.
        try {
            $this->carrierConfigRepository->get((int)$this->getRequest()->getParam('id'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Polkurier_Shipping::carriers');
        $page->getConfig()->getTitle()->prepend((string)__("Carrier settings"));

        return $page;
    }
}
