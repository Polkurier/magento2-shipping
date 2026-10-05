<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Ui\Component\Listing\Columns\Column;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\Source\ShipmentState;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;

/**
 * The Polkurier order of each Magento shipment, linked to its details. Left out for an order
 * placed with another carrier's method, which the module doesn't ship.
 */
class PolkurierOrderColumn extends Column
{
    private const string ACL_RESOURCE = 'Polkurier_Shipping::shipments';

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
        private readonly ShipmentState $states,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = [],
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepare(): void
    {
        parent::prepare();
        if (!$this->authorization->isAllowed(self::ACL_RESOURCE) || !$this->isForPolkurierOrder()) {
            $this->_data['config']['componentDisabled'] = true;
        }
    }

    /**
     * Whether the grid lists the shipments of an order placed with a Polkurier method.
     */
    private function isForPolkurierOrder(): bool
    {
        $orderId = (int)$this->getContext()->getRequestParam('order_id');
        if ($orderId === 0) {
            return true;
        }
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            return false;
        }

        return $order instanceof Order && Polkurier::isOrderOf($order);
    }

    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || !$this->authorization->isAllowed(self::ACL_RESOURCE)) {
            return $dataSource;
        }
        $ids = \array_filter(\array_map(
            static fn(array $item): int => (int)($item['entity_id'] ?? 0),
            $dataSource['data']['items'],
        ));
        $links = $this->getLinks($ids);
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $item[$name] = $links[(int)($item['entity_id'] ?? 0)] ?? '';
        }

        return $dataSource;
    }

    /**
     * @param int[] $magentoShipmentIds
     * @return array<int, string> links by Magento shipment id
     */
    private function getLinks(array $magentoShipmentIds): array
    {
        if ($magentoShipmentIds === []) {
            return [];
        }
        $states = $this->states->toArray();
        // A Magento shipment keeps every Polkurier order it has had: the one placed shows, or the
        // latest.
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::SHIPMENT_ID, ['in' => $magentoShipmentIds])
            ->setOrder(ShipmentInterface::ENTITY_ID, 'ASC');
        $links = [];
        $holding = [];
        foreach ($collection->getItems() as $shipment) {
            $magentoShipmentId = (int)$shipment->getShipmentId();
            if ($holding[$magentoShipmentId] ?? false) {
                continue;
            }
            $holding[$magentoShipmentId] = \in_array($shipment->getState(), MagentoShipment::HOLDING_STATES, true);
            $label = $shipment->getPolkurierNumber() ?? '#' . $shipment->getId();
            if ($shipment->getState() !== ShipmentInterface::STATE_CREATED) {
                $label .= ' (' . ($states[$shipment->getState()] ?? $shipment->getState()) . ')';
            }
            $links[$magentoShipmentId] = \sprintf(
                '<a href="%s">%s</a>',
                $this->escaper->escapeUrl($this->url->getUrl('polkurier/shipment/view', ['id' => (int)$shipment->getId()])),
                $this->escaper->escapeHtml($label),
            );
        }

        return $links;
    }
}
