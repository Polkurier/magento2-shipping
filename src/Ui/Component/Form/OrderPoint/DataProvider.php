<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\OrderPoint;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\ResourceModel\OrderPoint\CollectionFactory;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The pickup point the customer chose, shown on the order page.
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly OrderPointStore $pointStore,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $url,
        private readonly Config $config,
        private readonly MapTokenProvider $mapTokenProvider,
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * The form narrows by its own request parameter, not through the collection.
     */
    public function addFilter(Filter $filter): void
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        $orderId = (int)$this->request->getParam('order_id');
        $point = $orderId ? $this->pointStore->getForOrder($orderId) : null;
        $order = $orderId ? $this->getEditableOrder($orderId) : null;

        return [$orderId => [
            'order_id' => $orderId,
            'point_id' => $point?->getPointId() ?? '',
            'point_label' => $point?->getPointLabel() ?? '',
            'point_cod' => $point === null ? '' : ($point->isPointCod() ? (string)\__('Yes') : (string)\__('No')),
            // The point can be changed for orders placed with a Polkurier method.
            'editable' => $order !== null,
            'map' => $order === null ? null : $this->getMapConfig($order),
            'urls' => ['save' => $this->url->getUrl('polkurier/order/savePoint')],
        ]];
    }

    /**
     * What the map needs to open on the order's carrier and address, or null when it can't open:
     * no map token, or a carrier without pickup points.
     *
     * @return array<string, mixed>|null
     */
    private function getMapConfig(Order $order): ?array
    {
        $storeId = (int)$order->getStoreId();
        try {
            $token = $this->mapTokenProvider->getToken($storeId);
            $carrier = $this->catalog->getCarrier((string)Polkurier::methodCodeOf((string)$order->getShippingMethod()), $storeId);
        } catch (PolkurierException) {
            return null;
        }
        if ($token === '' || $carrier === null || !$this->features->isRecipientPointAllowed($carrier)) {
            return null;
        }
        $address = $order->getShippingAddress();

        return [
            'token' => $token,
            'baseUrl' => $this->config->getMapUrl($storeId) . '/',
            'providers' => $this->features->getPointProviders($carrier),
            'search' => \trim($address?->getPostcode() . ' ' . $address?->getCity()),
        ];
    }

    private function getEditableOrder(int $orderId): ?Order
    {
        if (!$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')) {
            return null;
        }
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception) {
            return null;
        }

        return $order instanceof Order && Polkurier::methodCodeOf((string)$order->getShippingMethod()) !== null
            ? $order
            : null;
    }
}
