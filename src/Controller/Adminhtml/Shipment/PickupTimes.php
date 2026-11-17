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
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Model\Shipment\PickupService;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

/**
 * Days and windows the chosen carrier can collect on, from the sender's postcode.
 */
class PickupTimes extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Magento_Sales::ship';
    public const string ROUTE = 'polkurier/shipment/pickupTimes';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly PickupService $pickupService,
        private readonly ErrorMessageResolver $errorMessageResolver,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $senderPostcode = $this->pickupService->getSenderPostcode($data);
        if (empty($data[ShipmentDraftInterface::FIELD_CARRIER_CODE])) {
            throw new LocalizedException(__('Choose a carrier first.'));
        }

        return ['days' => $this->pickupService->getPickupTimes(
            (string)$data[ShipmentDraftInterface::FIELD_CARRIER_CODE],
            $senderPostcode,
            isset($data[ShipmentDraftInterface::FIELD_RECIPIENT_POSTCODE])
                ? (string)$data[ShipmentDraftInterface::FIELD_RECIPIENT_POSTCODE]
                : null,
            (string)($data[ShipmentDraftInterface::FIELD_SHIPMENT_TYPE] ?? ShipmentTypeEnum::Box->value),
        )];
    }

    protected function buildRefusal(LocalizedException $e): array
    {
        // Retry is offered only when it may help. After a refusal, the screen waits for a change to
        // the shipment.
        return ['retry' => $this->errorMessageResolver->isTransient($e), 'days' => []];
    }
}
