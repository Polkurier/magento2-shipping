<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Carrier;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Controller\Adminhtml\ReadsPostData;
use Polkurier\Shipping\Model\Carrier\CarrierConfigHydrator;

/**
 * Saves how a carrier is offered and priced in checkout.
 */
class Save extends Action implements HttpPostActionInterface
{
    use ReadsPostData;

    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    /**
     * Where what was typed waits for the form, when it couldn't be saved.
     */
    public const string PERSISTOR_KEY = 'polkurier_carrier';

    public function __construct(
        Context $context,
        private readonly CarrierConfigRepositoryInterface $repository,
        private readonly CarrierConfigHydrator $hydrator,
        private readonly DataPersistorInterface $dataPersistor,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $data = $this->getPostData();
        $id = (int)($data['id'] ?? 0);
        $redirect = $this->resultRedirectFactory->create();
        try {
            $config = $this->repository->get($id);
            $this->hydrator->hydrate($config, $data);
            $this->repository->save($config);
            $this->messageManager->addSuccessMessage(\__('Saved.'));
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            // Back to the form with what was typed, as the other settings forms do.
            $this->dataPersistor->set(self::PERSISTOR_KEY, \array_diff_key($data, ['form_key' => true]));

            return $redirect->setPath('*/*/edit', ['id' => $id]);
        }

        return $this->isSaveAndContinue()
            ? $redirect->setPath('*/*/edit', ['id' => $id])
            : $redirect->setPath('*/*/index');
    }
}
