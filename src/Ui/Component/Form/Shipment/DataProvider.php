<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Shipment;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNotices;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Config\Source\PickupMode;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Config\Source\ShipmentType;
use Polkurier\Shipping\Model\Mapper\SenderMapper;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\OrderAmounts;
use Polkurier\Shipping\Model\Shipment\PlnAmounts;
use Polkurier\Shipping\Model\Shipment\OwnOrders;
use Polkurier\Shipping\Model\Shipment\RejectedDraft;
use Polkurier\Shipping\Model\Shipment\SendAgain;
use Polkurier\Shipping\Model\Shipment\ScreenContext;
use Polkurier\Shipping\Model\Shipment\PackKinds;
use Polkurier\Shipping\Model\Shipment\ShipmentOptions;
use Polkurier\Webservice\Request\Order\OrderAddressMessage;

/**
 * Data of the Polkurier shipment form: the draft for the order, the options it is put together
 * from, and what each carrier supports, for the form's scripts to act on.
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private ?array $loadedData = null;

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
        private readonly OwnOrders $ownOrders,
        private readonly ShipmentManagementInterface $management,
        private readonly DraftHydrator $hydrator,
        private readonly RejectedDraft $rejectedDraft,
        private readonly MasterData $masterData,
        private readonly ShipmentOptions $options,
        private readonly UrlInterface $url,
        private readonly ShipmentType $shipmentTypes,
        private readonly CodType $codTypes,
        private readonly CodReturnType $codReturnTypes,
        private readonly AdditionalService $services,
        private readonly PickupMode $pickupModes,
        private readonly SenderMapper $senderMapper,
        private readonly OrderPointStore $pointStore,
        private readonly CarrierNotices $carrierNotices,
        private readonly OrderAmounts $amounts,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly MagentoShipment $magentoShipment,
        private readonly ScreenContext $context,
        private readonly PackType $packTypes,
        private readonly SendAgain $sendAgain,
        private readonly DefaultDraftBuilder $draftBuilder,
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
     * @return array<int|string, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $orderId = (int)$this->request->getParam('order_id');
        if (!$orderId) {
            return $this->loadedData = [];
        }

        $order = $this->getOrder($orderId);
        $storeId = (int)$order->getStoreId();
        // Opened from a Magento shipment whose Polkurier order was cancelled: another goes on it,
        // with its items.
        $targetId = (int)$this->request->getParam('shipment_id') ?: null;
        if ($targetId !== null && !isset($this->magentoShipment->getFreeShipments($order)[$targetId])) {
            $targetId = null;
        }
        try {
            $data = $this->hydrator->toArray($targetId !== null
                ? $this->draftBuilder->createFromOrder($order, $this->magentoShipment->getShipmentQuantities($order, $targetId))
                : $this->management->getDefaultDraft($orderId));
        } catch (LocalizedException) {
            $data = [];
        }
        // Sending again a shipment that wasn't placed: from what was sent then. What was just
        // refused on this screen comes back over it.
        $data = \array_merge(
            $data,
            $this->sendAgain->find($order, $this->request->getParam(SendAgain::PARAM))?->getDraft() ?? [],
            $this->rejectedDraft->take($orderId) ?? [],
        );

        $data['order_id'] = $orderId;
        $data['order_increment_id'] = $order->getIncrementId();
        $data['store_id'] = $storeId;
        // Polkurier's amounts, prices, cash on delivery and insurance alike, are in złoty.
        $data['currency'] = PlnAmounts::CURRENCY;
        $amounts = $this->context->getAmounts($order);
        $data['grand_total'] = $amounts['grand_total'];
        $data['shipping_charged'] = $amounts['shipping_charged'];
        // What the products weigh, to start a parcel entered by hand from.
        $data['order_weight'] = \round((float)$order->getWeight(), 3);
        $targetId ??= isset($data['target_shipment_id']) ? (int)$data['target_shipment_id'] : null;
        $data['target_shipment_id'] = $targetId;
        // Items to choose only for a new Magento shipment of what's left.
        $data['can_ship'] = $targetId === null && $order->canShip();
        // A chosen Magento shipment, or with nothing left to ship, the latest without a Polkurier
        // order: the Polkurier order goes on it, in place of the one it replaces, with its items.
        $data['replacing'] = $data['can_ship'] ? null : $this->getReplaced($order, $targetId);
        $data['items'] = $this->getItems(
            $order,
            (array)($data['items'] ?? []),
            $data['replacing'] !== null ? $data['replacing']['qtys'] : null,
        );
        $data['shipment_value'] = $data['replacing'] !== null
            ? $data['replacing']['shipment_value']
            : $this->amounts->getShipmentValue($order, \array_column($data['items'], 'qty', 'order_item_id'), (bool)($data['shipping_included'] ?? false));
        $data['cod_collected'] = $amounts['cod_collected'];
        $data['cod_remaining'] = $amounts['cod_remaining'];
        // Another part includes the shipping and handling already.
        $data['shipping_covered'] = $amounts['shipping_covered'];
        // The items listed leave out those shipped in full: the screen can't tell from them alone.
        $data['partly_shipped'] = \array_sum(\array_map(
            static fn(array $item): float => $item['qty_shipped'],
            $this->amounts->getItems($order),
        )) > 0;
        $data['carriers'] = $this->options->getCarrierFeatures();
        // Offered for pallet shipments only.
        $data['pallet_services'] = \array_map(static fn($service): string => $service->value, CarrierFeatures::PALLET_SERVICES);
        $data['carriers_error'] = $this->options->getCarriersError();
        $data['map'] = $this->context->getMap();
        // The point as Polkurier has it now: its address today, or, when it's gone or closed since
        // the order was placed, a problem to fix before placing.
        $pointId = (string)($data['recipient_point_id'] ?? '');
        $point = $this->context->findRecipientPoint((string)($data['carrier_code'] ?? ''), $pointId);
        $data['recipient_point_label'] = $point ? $point->getAddress() : $this->getPointLabel($orderId, $pointId);
        $data['recipient_point_label_id'] = $pointId;
        $data['recipient_point_closed'] = $point === false ? $pointId : '';
        $data['senders'] = (object)$this->getSenders();
        $data['store_sender'] = $this->describeStoreSender($storeId);
        // Only those that can be printed as the sender.
        $data['cover_addresses'] = $this->options->getSenderOptions(true);
        $data['templates'] = $this->context->getTemplates();
        // What a shipment is until it has parcels: as the website's parcels go, as a new one
        // starts (DefaultDraftBuilder), for automatic packing.
        $data['default_shipment_type'] = $this->context->getDefaultShipmentType($storeId);
        $data['parcel_weight'] = $this->context->getParcelWeight($storeId);
        $data['carrier_notices'] = $this->carrierNotices->getNotices();
        $data['choices'] = $this->getChoices();
        $data['urls'] = [
            'calculate' => $this->url->getUrl('polkurier/shipment/calculate'),
            'pickup_times' => $this->url->getUrl('polkurier/shipment/pickupTimes'),
            'collective_pickup' => $this->url->getUrl('polkurier/shipment/collectivePickup'),
            'pack' => $this->url->getUrl('polkurier/shipment/pack'),
            'pack_templates' => $this->url->getUrl('polkurier/packtemplate/index'),
            'addresses' => $this->url->getUrl('polkurier/address/index'),
        ];

        return $this->loadedData = [$orderId => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $orderId = (int)$this->request->getParam('order_id');
        if (!$orderId) {
            return $meta;
        }
        $order = $this->getOrder($orderId);

        $senders = $this->options->getSenderChoices((int)$order->getStoreId());
        $carriers = \array_merge(
            [['value' => '', 'label' => (string)\__('-- Choose a carrier --')]],
            $this->options->getCarrierOptions(),
        );

        // Paths follow the form's modals.
        $meta['carrier_modal']['children']['carrier']['children']['carrier_code']['arguments']['data']['config']['options'] = $carriers;
        $meta['sender_modal']['children']['sender']['children']['sender_address_id']['arguments']['data']['config']['options'] = $senders;
        $meta['cod_modal']['children']['cod']['children']['cod_bank_account_id']['arguments']['data']['config']['options']
            = $this->options->getBankAccountChoices();
        $meta['cod_modal']['children']['cod']['children']['cod_amount']['arguments']['data']['config']['notice']
            = $this->getCodNotice($order);
        if (!$this->amounts->isPriced($order)) {
            $meta['insurance_modal']['children']['insurance']['children']['insurance_amount']['arguments']['data']['config']['notice']
                = $this->getCurrencyNotice($order);
        }

        return $meta;
    }

    /**
     * Every item the order has left to ship, with the quantity the draft ships (0 when it leaves
     * the item out, everything left without a draft), for the item list. Or, for a Polkurier order
     * going on a Magento shipment there is, that shipment's items, as it carries them.
     *
     * @param array<int, mixed> $draftItems rows of order_item_id and qty
     * @param array<int, float>|null $carried what the Magento shipment carries, by order item id
     * @return array<int, array<string, mixed>>
     */
    private function getItems(Order $order, array $draftItems, ?array $carried = null): array
    {
        if ($carried !== null) {
            $draftItems = \array_map(
                static fn(int $id, float $qty): array => ['order_item_id' => $id, 'qty' => $qty],
                \array_keys($carried),
                $carried,
            );
        }
        $chosen = [];
        foreach ($draftItems as $row) {
            if (\is_array($row) && isset($row['order_item_id'])) {
                $chosen[(int)$row['order_item_id']] = (float)\str_replace(',', '.', (string)($row['qty'] ?? 0));
            }
        }
        $items = [];
        foreach ($this->amounts->getItems($order) as $id => $item) {
            if ($carried !== null ? !isset($carried[$id]) : $item['qty_to_ship'] <= 0) {
                continue;
            }
            $items[] = [
                'order_item_id' => $id,
                'qty' => $chosen === [] ? $item['qty_to_ship'] : ($chosen[$id] ?? 0.0),
                'name' => $item['name'],
                'sku' => $item['sku'],
                'unit' => $item['unit'],
                'weight' => $item['weight'],
                'qty_ordered' => $item['qty'],
                'qty_shipped' => $item['qty_shipped'],
                'qty_to_ship' => $item['qty_to_ship'],
            ];
        }

        return $items;
    }

    /**
     * The Magento shipment a Polkurier order goes on, the one chosen or, when the order has
     * nothing left to ship, the latest without a Polkurier order, with the cancelled Polkurier
     * order it replaces and what the shipment carries; null when there is none.
     *
     * @return array{increment_id: string, replaces: string|null, shipment_value: float, worth: array{products: float, order_total: float, whole: bool, value: float}, qtys: array<int, float>}|null
     */
    private function getReplaced(Order $order, ?int $targetId): ?array
    {
        $free = $this->magentoShipment->getFreeShipments($order);
        $id = $targetId ?? ($free === [] ? null : \max(\array_keys($free)));
        if ($id === null || !isset($free[$id])) {
            return null;
        }
        $qtys = $this->magentoShipment->getShipmentQuantities($order, $id);

        $worth = $this->amounts->getShipmentWorth($order, $qtys);

        return $free[$id] + ['shipment_value' => $worth['value'], 'worth' => $worth, 'qtys' => $qtys];
    }

    /**
     * @throws LocalizedException when the order is gone or wasn't placed with a Polkurier method
     */
    private function getOrder(int $orderId): Order
    {
        return $this->ownOrders->get($orderId);
    }

    /**
     * How much cash on delivery is left to collect, when the order ships in parts.
     */
    private function getCodNotice(Order $order): string
    {
        if (!$this->amounts->isPriced($order)) {
            return $this->getCurrencyNotice($order);
        }
        $collected = $this->amounts->getCodCollected($order);
        if ($collected <= 0) {
            return (string)\__('0 or empty: no cash on delivery.');
        }

        return (string)\__(
            '0 or empty: no cash on delivery. Order total: %1, of which the order\'s other Polkurier orders collect %2.',
            $this->priceCurrency->format($this->amounts->getGrandTotal($order), false, 2, null, PlnAmounts::CURRENCY),
            $this->priceCurrency->format($collected, false, 2, null, PlnAmounts::CURRENCY),
        );
    }

    /**
     * Why an amount isn't filled in: the order is in a currency that can't be given in złoty.
     */
    private function getCurrencyNotice(Order $order): string
    {
        return (string)\__(
            'The order is in %1, which can\'t be converted to złoty here: enter the amount in złoty yourself.',
            (string)$order->getOrderCurrencyCode(),
        );
    }

    /**
     * The sender addresses, by id, as the screen shows them.
     *
     * @return array<string, array<string, string>>
     */
    private function getSenders(): array
    {
        $senders = [];
        foreach ($this->masterData->getSenderAddresses() as $address) {
            $senders[(string)$address->getId()] = $this->describeAddress($address);
        }

        return $senders;
    }

    /**
     * The address shipments go out from when no sender is chosen, or null when there is none.
     *
     * @return array<string, string>|null
     */
    private function describeStoreSender(int $storeId): ?array
    {
        $address = $this->senderMapper->fromStore($storeId);

        return $address === null ? null : $this->describeMessage($address);
    }

    /**
     * @return array<string, string>
     */
    private function describeAddress(AddressInterface $address): array
    {
        return [
            'label' => $address->getName(),
            'company' => (string)$address->getCompany(),
            'person' => $address->getPerson(),
            'street' => $this->street($address->getStreet(), $address->getHouseNumber(), $address->getFlatNumber()),
            'city' => \trim($address->getPostcode() . ' ' . $address->getCity()),
            'postcode' => $address->getPostcode(),
            'country' => $address->getCountry(),
            'phone' => $address->getPhone(),
            'email' => $address->getEmail(),
            'default' => $address->isDefault() ? '1' : '',
            // Where its parcels are handed over at a point.
            'point_id' => (string)$address->getPointId(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function describeMessage(OrderAddressMessage $address): array
    {
        return [
            'label' => (string)\__('The store\'s address'),
            'company' => (string)$address->getCompany(),
            'postcode' => $address->getPostcode(),
            'person' => $address->getPerson() === $address->getCompany() ? '' : $address->getPerson(),
            'street' => $this->street($address->getStreet(), (string)$address->getHouseNumber(), $address->getFlatNumber()),
            'city' => \trim($address->getPostcode() . ' ' . $address->getCity()),
            'country' => $address->getCountry(),
            'phone' => $address->getPhone(),
            'email' => $address->getEmail(),
            'default' => '',
        ];
    }

    private function street(string $street, string $houseNumber, ?string $flatNumber): string
    {
        return \trim($street . ' ' . $houseNumber . ($flatNumber ? '/' . $flatNumber : ''));
    }

    /**
     * The address of the order's pickup point, when the draft still uses it.
     */
    private function getPointLabel(int $orderId, string $pointId): string
    {
        $point = $pointId === '' ? null : $this->pointStore->getForOrder($orderId);

        return $point !== null && $point->getPointId() === $pointId ? (string)$point->getPointLabel() : '';
    }

    /**
     * Labels of every choice, by field, for the screen's summaries.
     *
     * @return array<string, object>
     */
    private function getChoices(): array
    {
        $labels = static function (array $options): object {
            $map = [];
            foreach ($options as $option) {
                $map[(string)$option['value']] = (string)$option['label'];
            }

            return (object)$map;
        };

        return [
            'carrier_code' => $labels($this->options->getCarrierOptions()),
            'shipment_type' => (object)$this->shipmentTypes->toArray(),
            'pack_type' => (object)$this->packTypes->toArray(),
            // What each parcel type makes the shipment.
            'pack_shipment_type' => (object)PackKinds::map(),
            'cod_type' => (object)$this->codTypes->toArray(),
            'cod_return_type' => (object)$this->codReturnTypes->toArray(),
            'cod_bank_account_id' => $labels($this->options->getBankAccountChoices()),
            'services' => (object)$this->services->toArray(),
            'pickup_mode' => (object)$this->pickupModes->toArray(),
        ];
    }
}
