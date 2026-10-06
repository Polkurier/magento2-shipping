<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Shipment\CostEstimator;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\OwnOrders;

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
        private readonly DraftHydrator $hydrator,
        private readonly OwnOrders $ownOrders,
        private readonly ErrorMessages $errorMessages,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $order = $this->ownOrders->get((int)($data['order_id'] ?? 0));
        $draft = $this->hydrator->fromArray($data);
        $quotes = $this->costEstimator->quoteAll($draft, (int)$order->getStoreId());
        $cost = $quotes[$draft->getCarrierCode()] ?? null;

        return [
            'error' => $cost === null,
            'message' => $cost === null ? (string)\__('Service unavailable') : '',
            'net' => $cost['net'] ?? null,
            'gross' => $cost['gross'] ?? null,
            'quotes' => (object)$quotes,
        ];
    }

    protected function refused(LocalizedException $e): array
    {
        // Offered again only when that may help; a refusal waits for a change to the shipment.
        return ['retry' => $this->errorMessages->isTransient($e), 'quotes' => (object)[]];
    }
}
