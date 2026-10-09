<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Valuation\CostEstimator;

/**
 * What Polkurier would charge for the shipment as currently filled in: with the chosen carrier, and
 * with every other carrier that can take it, so the screen can compare them.
 */
class Calculate extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly CostEstimator $costEstimator,
        private readonly DraftHydrator $draftHydrator,
        private readonly OrderLoader $orderLoader,
        private readonly ErrorMessageResolver $errorMessageResolver,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $order = $this->orderLoader->getForPolkurier((int)($data['order_id'] ?? 0));
        $draft = $this->draftHydrator->fromArray($data);
        $quotes = $this->costEstimator->quoteAll($draft, (int)$order->getStoreId());
        $cost = $quotes[$draft->getCarrierCode()] ?? null;

        return [
            'error' => $cost === null,
            'message' => $cost === null ? (string)__('Service unavailable') : '',
            'quotes' => (object)$quotes,
        ];
    }

    protected function refused(LocalizedException $e): array
    {
        // Retry is offered only when it may help. After a refusal, the screen waits for a change to
        // the shipment.
        return ['retry' => $this->errorMessageResolver->isTransient($e), 'quotes' => (object)[]];
    }
}
