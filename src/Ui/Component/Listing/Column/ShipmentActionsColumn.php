<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment\SendAgain;
use Polkurier\Shipping\Ui\Component\ShipmentConfirmations;

/**
 * What can be done with a Polkurier shipment, depending on its state.
 */
class ShipmentActionsColumn extends Column
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
        private readonly SendAgain $sendAgain,
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
            $id = (int)$item['entity_id'];
            $params = ['id' => $id, 'order_id' => (int)$item['order_id']];
            $actions = [
                'view' => [
                    'href' => $this->url->getUrl('polkurier/shipment/view', ['id' => $id]),
                    'label' => \__('Details'),
                ],
            ];
            // Not on the order's own page.
            if (!$this->getData('config/onOrderPage')) {
                $actions['order'] = [
                    'href' => $this->url->getUrl('sales/order/view', ['order_id' => (int)$item['order_id']]),
                    'label' => \__('View order'),
                ];
            }
            switch ($item['state']) {
                case ShipmentInterface::STATE_CREATED:
                    $actions['label'] = ['href' => $this->url->getUrl('polkurier/shipment/label', $params), 'label' => \__('Print label')];
                    $actions['protocol'] = ['href' => $this->url->getUrl('polkurier/shipment/protocol', $params), 'label' => \__('Print handover protocol')];
                    $actions['refresh'] = [
                        'href' => $this->url->getUrl('polkurier/shipment/refresh', $params),
                        'label' => \__('Update status'),
                        'post' => true,
                    ];
                    if (empty($item['shipment_id']) && $this->authorization->isAllowed('Polkurier_Shipping::shipment_create')) {
                        $actions['ship'] = [
                            'href' => $this->url->getUrl('polkurier/shipment/ship', $params),
                            'label' => \__('Create Magento shipment'),
                            'post' => true,
                        ];
                    }
                    if ($this->authorization->isAllowed('Polkurier_Shipping::shipment_cancel')) {
                        $actions['cancel'] = [
                            'href' => $this->url->getUrl('polkurier/shipment/cancel', $params),
                            'label' => \__('Cancel'),
                            'post' => true,
                            'confirm' => ShipmentConfirmations::cancel((string)$item['polkurier_number']),
                        ];
                    }
                    break;
                case ShipmentInterface::STATE_FAILED:
                    if ($this->authorization->isAllowed('Polkurier_Shipping::shipment_create')
                        && $this->sendAgain->isPossibleFor($id, (int)$item['order_id'], ShipmentInterface::STATE_FAILED)
                    ) {
                        $actions['retry'] = [
                            'href' => $this->sendAgain->getUrl($id, (int)$item['order_id']),
                            'label' => \__('Send again'),
                        ];
                    }
                    if ($this->authorization->isAllowed('Polkurier_Shipping::shipment_create')) {
                        $actions['discard'] = [
                            'href' => $this->url->getUrl('polkurier/shipment/discard', $params),
                            'label' => \__('Discard'),
                            'post' => true,
                            'confirm' => ShipmentConfirmations::discard(),
                        ];
                    }
                    break;
                case ShipmentInterface::STATE_UNKNOWN:
                case ShipmentInterface::STATE_PENDING:
                    if ($this->authorization->isAllowed('Polkurier_Shipping::shipment_create')) {
                        $actions['reconcile'] = [
                            'href' => $this->url->getUrl('polkurier/shipment/reconcile', $params),
                            'label' => \__('Reconcile'),
                            'post' => true,
                        ];
                    }
                    break;
            }
            $item[$this->getData('name')] = $actions;
        }

        return $dataSource;
    }
}
