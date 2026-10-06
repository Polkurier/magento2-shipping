<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
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
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly CarrierConfigRepositoryInterface $repository,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        // Carriers come from Polkurier's list, so there is no new one to open.
        try {
            $this->repository->get((int)$this->getRequest()->getParam('id'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if (!$page instanceof Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu('Polkurier_Shipping::carriers');
        $page->getConfig()->getTitle()->prepend((string)\__("Carrier settings"));

        return $page;
    }
}
