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
use Polkurier\Shipping\Model\Shipment\Pickups;
use Polkurier\Shipping\Model\Shipment\SenderPostcode;

/**
 * Days and windows the chosen carrier can collect on, from the sender's postcode.
 */
class PickupTimes extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly Pickups $pickups,
        private readonly ErrorMessages $errorMessages,
        private readonly SenderPostcode $senderPostcode,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $senderPostcode = $this->senderPostcode->of($data);
        if (empty($data['carrier_code'])) {
            throw new LocalizedException(\__('Choose a carrier first.'));
        }

        return ['days' => $this->pickups->getPickupTimes(
            (string)$data['carrier_code'],
            $senderPostcode,
            isset($data['recipient_postcode']) ? (string)$data['recipient_postcode'] : null,
            (string)($data['shipment_type'] ?? 'box'),
        )];
    }

    protected function refused(LocalizedException $e): array
    {
        // Offered again only when that may help; a refusal waits for a change to the shipment.
        return ['retry' => $this->errorMessages->isTransient($e), 'days' => []];
    }
}
