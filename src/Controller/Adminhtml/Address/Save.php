<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Address;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\PageFactory;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\Index as BaseAction;
use Polkurier\Shipping\Exception\NotFoundException;
use Polkurier\Shipping\Model\Address;
use Polkurier\Shipping\Model\AddressFactory;
use Psr\Log\LoggerInterface;

class Save extends BaseAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::addresses';
    public const string DATA_PERSISTOR_KEY = 'polkurier_address';

    /**
     * @param AddressRepositoryInterface $addressRepository
     * @param AddressFactory $addressFactory
     * @param DataPersistorInterface $dataPersistor
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly AddressFactory $addressFactory,
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
        $addressData = $this->getRequest()->getPostValue();
        if (!$addressData) {
            return $resultRedirect->setPath('*/*/');
        }

        $addressId = (int)($addressData[Address::FIELD_ADDRESS_ID] ?? 0);
        try {
            if ($addressId) {
                $address = $this->addressRepository->findOneById($addressId);
                if (!$address) {
                    throw new NotFoundException(\__('This address no longer exists.'));
                }
            } else {
                $address = $this->addressFactory->create();
            }

            $address->addData($addressData);
            $this->addressRepository->save($address);
            $this->messageManager->addSuccessMessage(\__('The address has been saved.'));
            $this->dataPersistor->clear(static::DATA_PERSISTOR_KEY);
            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['id' => $address->getId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (NotFoundException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager
                ->addExceptionMessage($e, \__('Something went wrong while saving the address.'));
        }

        $this->dataPersistor->set(static::DATA_PERSISTOR_KEY, $addressData);

        return $resultRedirect->setPath('*/*/edit', ['id' => $addressId ?: null]);
    }
}
