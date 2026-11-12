<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;
use Polkurier\Shipping\Ui\Component\Control\OnClickScriptBuilder;

/**
 * For a shipment that wasn't placed: opens the shipment screen, starting from that shipment.
 */
class SendAgainButton extends AbstractButton
{
    public function __construct(
        RequestInterface $request,
        UrlInterface $url,
        AuthorizationInterface $authorization,
        ShipmentRepositoryInterface $shipmentRepository,
        OnClickScriptBuilder $onClickScriptBuilder,
        private readonly RetriableShipmentFinder $retriableShipmentFinder,
    ) {
        parent::__construct($request, $url, $authorization, $shipmentRepository, $onClickScriptBuilder);
    }

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $shipment = $this->getShipment();
        if ($shipment === null
            || !$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
            || !$this->retriableShipmentFinder->isPossible($shipment)
        ) {
            return [];
        }

        return [
            'label' => (string)__('Send again'),
            'class' => 'primary',
            'on_click' => $this->onClickScriptBuilder->buildLocation(
                $this->url->getUrl('polkurier/shipment/create', [
                    'order_id' => $shipment->getOrderId(),
                    RetriableShipmentFinder::PARAM => (int)$shipment->getId(),
                ]),
            ),
            'sort_order' => 30,
        ];
    }
}
