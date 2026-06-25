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
use Magento\Framework\View\Result\PageFactory;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;
use Psr\Log\LoggerInterface;

class InlineEdit extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';

    /**
     * @param PackTemplateRepositoryInterface $packTemplateRepository
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        private readonly PackTemplateRepositoryInterface $packTemplateRepository,
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
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        $postItems = $this->getRequest()->getParam('items', []);
        if (!($this->getRequest()->getParam('isAjax') && \count($postItems))) {
            return $resultJson->setData([
                'messages' => [\__('Please correct the data sent.')],
                'error' => true,
            ]);
        }
        foreach ($postItems as $packTemplateId => $packTemplateData) {
            $packTemplate = $this->packTemplateRepository->findOneById((int)$packTemplateId);
            if ($packTemplate === null) {
                $this->addError(\__('Template #$1 not found', $packTemplateId));
                continue;
            }
            $packTemplate->addData($packTemplateData);
            $this->packTemplateRepository->save($packTemplate);
        }

        return $resultJson->setData([
            'messages' => $this->getErrorMessages(),
            'error' => $this->isErrorExists(),
        ]);
    }
}
