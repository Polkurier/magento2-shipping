<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;

/**
 * The details of one Polkurier shipment.
 */
class View extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Magento_Sales::shipment';
    public const string ROUTE = 'polkurier/shipment/view';

    /**
     * Also opened from the admin's notifications, whose links are made outside the session and so
     * have no URL key. The page only displays data, and the ACL still applies.
     *
     * @var string[]
     */
    protected $_publicActions = ['view'];

    public function __construct(
        Context $context,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        try {
            $shipment = $this->shipmentRepository->get((int)$this->getRequest()->getParam('id'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This Polkurier shipment no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Polkurier_Shipping::shipments');
        $page->getConfig()->getTitle()->prepend((string)__(
            'Shipment details %1',
            $shipment->getPolkurierNumber() ?: '#' . $shipment->getId(),
        ));

        return $page;
    }
}
