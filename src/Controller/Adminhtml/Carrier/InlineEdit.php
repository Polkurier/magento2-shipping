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
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigHydrator;

/**
 * Label, visibility and a fixed price edited straight in the carriers grid.
 */
class InlineEdit extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CarrierConfigRepositoryInterface $carrierConfigRepository,
        private readonly CarrierConfigHydrator $carrierConfigHydrator,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $messages = [];
        foreach ((array)$this->getRequest()->getParam('items', []) as $id => $row) {
            if (!\is_array($row)) {
                continue;
            }
            try {
                $config = $this->carrierConfigRepository->get((int)$id);
                $this->carrierConfigHydrator->applyRow($config, $row);
                $this->carrierConfigRepository->save($config);
            } catch (LocalizedException $e) {
                $messages[] = $e->getMessage();
            }
        }

        return $this->jsonFactory->create()->setData(['messages' => $messages, 'error' => $messages !== []]);
    }
}
