<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\OrderPointEditor;

/**
 * Changes an order's pickup point, from the Polkurier box on the order page.
 */
class SavePoint extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly OrderPointEditor $editor,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            $point = $this->editor->change(
                (int)$this->getRequest()->getParam('order_id'),
                (string)$this->getRequest()->getParam('point_id'),
            );
        } catch (LocalizedException $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        }

        return $result->setData([
            'error' => false,
            'message' => (string)\__('The pickup point is saved.'),
            'point_id' => $point?->getPointId() ?? '',
            'point_label' => $point?->getPointLabel() ?? '',
            'point_cod' => $point === null ? '' : ($point->isPointCod() ? (string)\__('Yes') : (string)\__('No')),
        ]);
    }
}
