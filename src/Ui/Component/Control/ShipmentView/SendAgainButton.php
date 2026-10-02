<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Shipment\SendAgain;

/**
 * For a shipment that wasn't placed: the shipment screen, starting from it.
 */
class SendAgainButton extends AbstractButton
{
    public function __construct(
        RequestInterface $request,
        UrlInterface $url,
        AuthorizationInterface $authorization,
        ShipmentRepositoryInterface $shipmentRepository,
        Json $json,
        private readonly SendAgain $sendAgain,
    ) {
        parent::__construct($request, $url, $authorization, $shipmentRepository, $json);
    }

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $shipment = $this->shipment();
        if ($shipment === null
            || !$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
            || !$this->sendAgain->isPossible($shipment)
        ) {
            return [];
        }

        return [
            'label' => (string)\__('Send again'),
            'class' => 'primary',
            'on_click' => \sprintf("setLocation('%s');", $this->sendAgain->getUrl((int)$shipment->getId(), $shipment->getOrderId())),
            'sort_order' => 30,
        ];
    }
}
