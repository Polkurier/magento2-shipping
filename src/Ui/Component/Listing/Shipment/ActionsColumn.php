<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Shipment;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment\CancellationRule;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;
use Polkurier\Shipping\Model\ShipmentFactory;
use Polkurier\Shipping\Ui\Component\ShipmentConfirmationProvider;

/**
 * What can be done with a Polkurier shipment, depending on its state.
 */
class ActionsColumn extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $url,
        private readonly AuthorizationInterface $authorization,
        private readonly RetriableShipmentFinder $retriableShipmentFinder,
        private readonly ShipmentFactory $shipmentFactory,
        array $components = [],
        array $data = [],
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string, mixed> $dataSource
     *
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int)$item[ShipmentInterface::FIELD_ENTITY_ID];
            $params = ['id' => $id, 'order_id' => (int)$item[ShipmentInterface::FIELD_ORDER_ID]];
            $actions = [
                'view' => [
                    'href' => $this->url->getUrl('polkurier/shipment/view', ['id' => $id]),
                    'label' => __('Details'),
                ],
            ];
            // Not on the order's own page.
            if (!$this->getData('config/onOrderPage')) {
                $actions['order'] = [
                    'href' => $this->url->getUrl(
                        'sales/order/view',
                        ['order_id' => (int)$item[ShipmentInterface::FIELD_ORDER_ID]],
                    ),
                    'label' => __('View order'),
                ];
            }
            switch ($item[ShipmentInterface::FIELD_STATE]) {
                case ShipmentInterface::STATE_CREATED:
                    $actions['label'] = [
                        'href' => $this->url->getUrl('polkurier/shipment/label', $params),
                        'label' => __('Print label'),
                    ];
                    $actions['protocol'] = [
                        'href' => $this->url->getUrl('polkurier/shipment/protocol', $params),
                        'label' => __('Print handover protocol'),
                    ];
                    $actions['refresh'] = [
                        'href' => $this->url->getUrl('polkurier/shipment/refresh', $params),
                        'label' => __('Update status'),
                        'post' => true,
                    ];
                    if (empty($item[ShipmentInterface::FIELD_SHIPMENT_ID])
                        && $this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
                    ) {
                        $actions['ship'] = [
                            'href' => $this->url->getUrl('polkurier/shipment/ship', $params),
                            'label' => __('Create Magento shipment'),
                            'post' => true,
                        ];
                    }
                    if ($this->authorization->isAllowed('Polkurier_Shipping::shipment_cancel')
                        && CancellationRule::refusal($this->shipmentFactory->create(['data' => $item])) === null
                    ) {
                        $actions['cancel'] = [
                            'href' => $this->url->getUrl('polkurier/shipment/cancel', $params),
                            'label' => __('Cancel'),
                            'post' => true,
                            'confirm' => ShipmentConfirmationProvider::cancel(
                                (string)$item[ShipmentInterface::FIELD_POLKURIER_NUMBER],
                            ),
                        ];
                    }
                    break;
                case ShipmentInterface::STATE_FAILED:
                    if ($this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
                        && $this->retriableShipmentFinder->isPossibleFor(
                            $id,
                            (int)$item[ShipmentInterface::FIELD_ORDER_ID],
                            ShipmentInterface::STATE_FAILED,
                        )
                    ) {
                        $actions['retry'] = [
                            'href' => $this->url->getUrl('polkurier/shipment/create', [
                                'order_id' => (int)$item[ShipmentInterface::FIELD_ORDER_ID],
                                RetriableShipmentFinder::PARAM => $id,
                            ]),
                            'label' => __('Send again'),
                        ];
                    }
                    if ($this->authorization->isAllowed('Polkurier_Shipping::shipment_create')) {
                        $actions['discard'] = [
                            'href' => $this->url->getUrl('polkurier/shipment/discard', $params),
                            'label' => __('Discard'),
                            'post' => true,
                            'confirm' => ShipmentConfirmationProvider::discard(),
                        ];
                    }
                    break;
            }
            $item[$this->getData('name')] = $actions;
        }

        return $dataSource;
    }
}
