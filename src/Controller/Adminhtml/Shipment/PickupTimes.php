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
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Model\Mapper\SenderMapper;
use Polkurier\Shipping\Model\Shipment\OwnOrders;
use Polkurier\Shipping\Model\Shipment\Pickups;

/**
 * Days and windows the chosen carrier can collect on, from the sender's postcode.
 */
class PickupTimes extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Pickups $pickups,
        private readonly OwnOrders $ownOrders,
        private readonly ErrorMessages $errorMessages,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly SenderMapper $senderMapper,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $data = (array)$this->getRequest()->getParam('data', []);
        try {
            $storeId = (int)$this->ownOrders->get((int)($data['order_id'] ?? 0))->getStoreId();
            $senderId = (int)($data['sender_address_id'] ?? 0);
            $senderPostcode = $senderId
                ? $this->addressRepository->get($senderId)->getPostcode()
                : $this->senderMapper->fromStore($storeId)?->getPostcode();
            if ($senderPostcode === null) {
                throw new LocalizedException(\__('Choose the sender address first.'));
            }
            if (empty($data['carrier_code'])) {
                throw new LocalizedException(\__('Choose a carrier first.'));
            }
            $days = $this->pickups->getPickupTimes(
                (string)$data['carrier_code'],
                $senderPostcode,
                isset($data['recipient_postcode']) ? (string)$data['recipient_postcode'] : null,
                (string)($data['shipment_type'] ?? 'box'),
            );
        } catch (LocalizedException $e) {
            return $result->setData([
                'error' => true,
                'message' => $e->getMessage(),
                // Offered again only when that may help; a refusal waits for a change to the shipment.
                'retry' => $this->errorMessages->isTransient($e),
                'days' => [],
            ]);
        }

        return $result->setData(['error' => false, 'days' => $days]);
    }
}
