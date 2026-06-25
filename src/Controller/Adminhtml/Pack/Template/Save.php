<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Pack\Template;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\PageFactory;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;
use Polkurier\Shipping\Exception\NotFoundException;
use Polkurier\Shipping\Model\PackTemplateFactory;
use Polkurier\Shipping\Model\PackTemplate;
use Psr\Log\LoggerInterface;

class Save extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::pack_templates';
    public const string DATA_PERSISTOR_KEY = 'polkurier_pack_template';

    /**
     * @param PackTemplateRepositoryInterface $packTemplateRepository
     * @param PackTemplateFactory $packTemplateFactory
     * @param DataPersistorInterface $dataPersistor
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        private readonly PackTemplateRepositoryInterface $packTemplateRepository,
        private readonly PackTemplateFactory $packTemplateFactory,
        private readonly DataPersistorInterface $dataPersistor,
        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        Context $context
    ) {
        parent::__construct($resultPageFactory, $resultForwardFactory, $resultJsonFactory, $logger, $context);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $packTemplateData = $this->getRequest()->getPostValue();
        if (!$packTemplateData) {
            return $resultRedirect->setPath('*/*/');
        }

        $packTemplateId = (int)($packTemplateData[PackTemplate::FIELD_TEMPLATE_ID] ?? 0);
        try {
            if ($packTemplateId) {
                $packTemplate = $this->packTemplateRepository->findOneById($packTemplateId);
                if (!$packTemplate) {
                    throw new NotFoundException(\__('This template no longer exists.'));
                }
            } else {
                $packTemplate = $this->packTemplateFactory->create();
            }

            $packTemplate->addData($packTemplateData);
            $this->packTemplateRepository->save($packTemplate);
            $this->messageManager->addSuccessMessage(\__('The template has been saved.'));
            $this->dataPersistor->clear(static::DATA_PERSISTOR_KEY);
            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $packTemplate->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (NotFoundException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager
                ->addExceptionMessage($e, \__('Something went wrong while saving the template.'));
        }

        $this->dataPersistor->set(static::DATA_PERSISTOR_KEY, $packTemplateData);

        return $resultRedirect->setPath('*/*/edit', ['id' => $packTemplateId ?: null]);
    }
}
