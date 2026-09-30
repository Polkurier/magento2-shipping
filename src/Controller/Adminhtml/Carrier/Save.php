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
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierConfigHydrator;

/**
 * Saves how a carrier is offered and priced in checkout.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly CarrierConfigRepositoryInterface $repository,
        private readonly CarrierConfigHydrator $hydrator,
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
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/edit', ['id' => $id]);
        }

        return $this->getRequest()->getParam('back')
            ? $redirect->setPath('*/*/edit', ['id' => $id])
            : $redirect->setPath('*/*/index');
    }

    /**
     * @return array<string, mixed>
     */
    private function getPostData(): array
    {
        $request = $this->getRequest();

        return $request instanceof \Magento\Framework\App\Request\Http ? (array)$request->getPostValue() : [];
    }
}
