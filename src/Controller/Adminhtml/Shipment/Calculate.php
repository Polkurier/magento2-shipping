<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Shipment\CostEstimator;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\OwnOrders;

/**
 * What Polkurier would charge for the shipment as currently filled in: with the chosen carrier, and
 * with every other carrier that can take it, so the screen can compare them.
 */
class Calculate extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CostEstimator $costEstimator,
        private readonly DraftHydrator $hydrator,
        private readonly OwnOrders $ownOrders,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $data = (array)$this->getRequest()->getParam('data', []);
        try {
            $order = $this->ownOrders->get((int)($data['order_id'] ?? 0));
            $draft = $this->hydrator->fromArray($data);
            $quotes = $this->costEstimator->quoteAll($draft, (int)$order->getStoreId());
        } catch (LocalizedException $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage(), 'quotes' => (object)[]]);
        }
        $cost = $quotes[$draft->getCarrierCode()] ?? null;

        return $result->setData([
            'error' => $cost === null,
            'message' => $cost === null ? (string)\__('The carrier does not price this shipment.') : '',
            'net' => $cost['net'] ?? null,
            'gross' => $cost['gross'] ?? null,
            'quotes' => (object)$quotes,
        ]);
    }
}
