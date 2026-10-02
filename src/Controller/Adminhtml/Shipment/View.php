<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;

/**
 * The details of one Polkurier shipment.
 */
class View extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    /**
     * Opened from the admin's notifications too, whose links are made outside the session: no URL
     * key. The page only shows; the ACL still applies.
     *
     * @var string[]
     */
    protected $_publicActions = ['view'];

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        try {
            $shipment = $this->shipmentRepository->get((int)$this->getRequest()->getParam('id'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(\__('This Polkurier shipment no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $page = $this->pageFactory->create();
        if (!$page instanceof \Magento\Backend\Model\View\Result\Page) {
            throw new \LogicException('The admin area renders backend pages.');
        }
        $page->setActiveMenu('Polkurier_Shipping::shipments');
        $page->getConfig()->getTitle()->prepend((string)\__(
            'Shipment details %1',
            $shipment->getPolkurierNumber() ?: '#' . $shipment->getId(),
        ));

        return $page;
    }
}
