<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Shipment;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * The number of the Magento shipment a Polkurier shipment went on, linked to it. Empty when there
 * is none, e.g. for a shipment that wasn't placed.
 */
class MagentoShipmentLinkColumn extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly UrlInterface $url,
        private readonly AuthorizationInterface $authorization,
        private readonly Escaper $escaper,
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
        $ids = \array_filter(\array_map(
            static fn(array $item): int => (int)($item[ShipmentInterface::FIELD_SHIPMENT_ID] ?? 0),
            $dataSource['data']['items'],
        ));
        $numbers = [];
        if ($ids !== []) {
            $shipments = $this->collectionFactory->create()->addFieldToFilter('entity_id', ['in' => $ids])->getItems();
            foreach ($shipments as $shipment) {
                $numbers[(int)$shipment->getId()] = (string)$shipment->getIncrementId();
            }
        }
        $linked = $this->authorization->isAllowed('Magento_Sales::shipment');
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int)($item[ShipmentInterface::FIELD_SHIPMENT_ID] ?? 0);
            if (!isset($numbers[$id])) {
                $item[$name] = '';
                continue;
            }
            $label = $this->escaper->escapeHtml('#' . $numbers[$id]);
            $item[$name] = $linked
                ? \sprintf(
                    '<a href="%s">%s</a>',
                    $this->escaper->escapeUrl(
                        $this->url->getUrl('adminhtml/order_shipment/view', ['shipment_id' => $id]),
                    ),
                    $label,
                )
                : $label;
        }

        return $dataSource;
    }
}
