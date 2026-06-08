<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Pack\Template;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\Collection;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory;
use Psr\Log\LoggerInterface;

class MassDelete extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    /**
     * @param PackTemplateRepositoryInterface $packTemplateRepository
     * @param CollectionFactory $collectionFactory
     * @param Filter $filter
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        /**
         * @var PackTemplateRepositoryInterface
         */
        private readonly PackTemplateRepositoryInterface $packTemplateRepository,

        /**
         * @var CollectionFactory
         */
        private readonly CollectionFactory $collectionFactory,

        /**
         * @var Filter
         */
        private readonly Filter $filter,

        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        Context $context
    ) {
        parent::__construct($resultPageFactory, $resultForwardFactory, $resultJsonFactory, $logger, $context);
    }

    /**
     * @return Json
     * @throws LocalizedException
     */
    public function execute(): Json
    {
        /** @var Collection $collection */
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $error = false;
        try {
            /** @var PackTemplate $packTemplate */
            foreach ($collection as $packTemplate) {
                $this->packTemplateRepository->delete($packTemplate);
            }
            $message = __('A total of %1 record(s) have been deleted.', $collection->getSize());
        } catch (\Exception $e) {
            $message = __('We can\'t mass delete the templates right now.');
            $error = true;
            $this->logger->critical($e);
        }

        return $this->resultJsonFactory->create(['error' => $error, 'message' => $message]);
    }
}
