<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Shipment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Helper\Data as SalesData;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipment;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraft\ItemInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraft\PackInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNoticeProvider;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Config\Source\PickupMode;
use Polkurier\Shipping\Model\Config\Source\ShipmentType;
use Polkurier\Shipping\Model\PickupPoint\AdminMapConfigProvider;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Shipping\Model\Shipment\Placement\RejectedDraftStorage;
use Polkurier\Shipping\Model\Shipment\Placement\SenderMapper;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Screen\ScreenDataProvider;
use Polkurier\Shipping\Model\Shipment\Screen\ShipmentOptionsProvider;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Shipping\Ui\Component\Form\FormDataProvider;
use Polkurier\Webservice\Entity\Order as RemoteOrder;
use Polkurier\Webservice\Entity\Order\Address;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;

/**
 * Data of the Polkurier shipment form: the draft for the order, the options it is put together
 * from, and what each carrier supports, for the form's scripts to act on.
 */
class DataProvider extends FormDataProvider
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
        private readonly OrderLoader $orderLoader,
        private readonly RejectedDraftStorage $rejectedDraftStorage,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly ShipmentOptionsProvider $shipmentOptionsProvider,
        private readonly UrlInterface $url,
        private readonly ShipmentType $shipmentTypes,
        private readonly CodType $codTypes,
        private readonly CodReturnType $codReturnTypes,
        private readonly AdditionalService $services,
        private readonly PickupMode $pickupModes,
        private readonly SenderMapper $senderMapper,
        private readonly CarrierNoticeProvider $carrierNoticeProvider,
        private readonly OrderAmountCalculator $orderAmountCalculator,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly NativeShipmentService $nativeShipmentService,
        private readonly ScreenDataProvider $screenDataProvider,
        private readonly PackType $packTypes,
        private readonly RetriableShipmentFinder $retriableShipmentFinder,
        private readonly ConfigProvider $configProvider,
        private readonly SalesData $salesData,
        private readonly AdminMapConfigProvider $adminMapConfigProvider,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
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
        $order = $this->orderLoader->getForPolkurier($orderId);

        $senders = $this->shipmentOptionsProvider->getSenderChoices((int)$order->getStoreId());
        $carriers = \array_merge(
            [['value' => '', 'label' => (string)__('-- Choose a carrier --')]],
            $this->shipmentOptionsProvider->getCarrierOptions(),
        );

        self::setFieldConfig($meta, 'carrier', ShipmentDraftInterface::FIELD_CARRIER_CODE, 'options', $carriers);
        self::setFieldConfig($meta, 'sender', ShipmentDraftInterface::FIELD_SENDER_ADDRESS_ID, 'options', $senders);
        self::setFieldConfig(
            $meta,
            'cod',
            ShipmentDraftInterface::FIELD_COD_BANK_ACCOUNT_ID,
            'options',
            $this->shipmentOptionsProvider->getBankAccountChoices(),
        );
        self::setFieldConfig(
            $meta,
            'shipment',
            ShipmentDraftInterface::FIELD_DESCRIPTION,
            'notice',
            $this->screenDataProvider->getDescriptionNotice(),
        );
        self::setFieldConfig(
            $meta,
            'cod',
            ShipmentDraftInterface::FIELD_COD_AMOUNT,
            'notice',
            $this->getCodNotice($order),
        );
        if (!$this->orderAmountCalculator->isPriced($order)) {
            self::setFieldConfig(
                $meta,
                'insurance',
                ShipmentDraftInterface::FIELD_INSURANCE_AMOUNT,
                'notice',
                $this->getCurrencyNotice($order),
            );
        }

        return $meta;
    }

    protected function load(): array
    {
        $orderId = (int)$this->request->getParam('order_id');
        if (!$orderId) {
            return [];
        }

        $order = $this->orderLoader->getForPolkurier($orderId);
        $storeId = (int)$order->getStoreId();
        // Opened from a Magento shipment whose Polkurier order was cancelled: the new one goes on
        // that shipment, with its items.
        $targetId = (int)$this->request->getParam('shipment_id') ?: null;
        if ($targetId !== null && !isset($this->nativeShipmentService->getFreeShipments($order)[$targetId])) {
            $targetId = null;
        }
        $data = $this->screenDataProvider->getDefaultValues(
            $order,
            $targetId !== null ? $this->nativeShipmentService->getShipmentQuantities($order, $targetId) : null,
        );
        // Sending again a shipment that wasn't placed starts from what was sent then. What this
        // screen just had refused is laid over it.
        $data = \array_merge(
            $data,
            $this->retriableShipmentFinder
                ->find($order, $this->request->getParam(RetriableShipmentFinder::PARAM))
                ?->getDraft() ?? [],
            $this->rejectedDraftStorage->take($orderId) ?? [],
        );

        $data['order_id'] = $orderId;
        $data['order_increment_id'] = $order->getIncrementId();
        $data['store_id'] = $storeId;
        // Polkurier's amounts, prices, cash on delivery and insurance alike, are in złoty.
        $data['currency'] = OrderAmountCalculator::CURRENCY;
        $amounts = $this->screenDataProvider->getAmounts($order);
        $data['grand_total'] = $amounts['grand_total'];
        $data['shipping_charged'] = $amounts['shipping_charged'];
        // What the products weigh, to start a parcel entered by hand from.
        $data['order_weight'] = \round((float)$order->getWeight(), 3);
        $targetId ??= isset($data[ShipmentDraftInterface::FIELD_TARGET_SHIPMENT_ID])
            ? (int)$data[ShipmentDraftInterface::FIELD_TARGET_SHIPMENT_ID]
            : null;
        $data[ShipmentDraftInterface::FIELD_TARGET_SHIPMENT_ID] = $targetId;
        // Items to choose only for a new Magento shipment of what's left.
        $data['can_ship'] = $targetId === null && $order->canShip();
        // The Polkurier order goes on a Magento shipment, with its items, in place of the one it
        // replaces: the chosen shipment or, with nothing left to ship, the latest without a
        // Polkurier order.
        $data['replacing'] = $data['can_ship'] ? null : $this->getReplaced($order, $targetId);
        // The customer's e-mail, for a new Magento shipment or one nobody was e-mailed about yet,
        // while the store sends shipment e-mails.
        $data['notify_offered'] = ($data['can_ship'] || ($data['replacing']['email_open'] ?? false))
            && $this->salesData->canSendNewShipmentEmail($storeId);
        $data[ShipmentDraftInterface::FIELD_ITEMS] = $this->getItems(
            $order,
            (array)($data[ShipmentDraftInterface::FIELD_ITEMS] ?? []),
            $data['replacing'] !== null ? $data['replacing']['qtys'] : null,
        );
        $data['shipment_value'] = $data['replacing'] !== null
            ? $data['replacing']['shipment_value']
            : $this->orderAmountCalculator->getShipmentValue(
                $order,
                \array_column(
                    $data[ShipmentDraftInterface::FIELD_ITEMS],
                    ItemInterface::FIELD_QTY,
                    ItemInterface::FIELD_ORDER_ITEM_ID,
                ),
                (bool)($data[ShipmentDraftInterface::FIELD_SHIPPING_INCLUDED] ?? false),
            );
        $data['cod_collected'] = $amounts['cod_collected'];
        $data['cod_remaining'] = $amounts['cod_remaining'];
        // Another part includes the shipping and handling already.
        $data['shipping_covered'] = $amounts['shipping_covered'];
        // The items listed leave out those shipped in full: the screen can't tell from them alone.
        $data['partly_shipped'] = \array_sum(\array_map(
            static fn(array $item): float => $item['qty_shipped'],
            $this->orderAmountCalculator->getItems($order),
        )) > 0;
        $data['carriers'] = $this->shipmentOptionsProvider->getCarrierFeatures();
        // Offered for pallet shipments only.
        $data['pallet_services'] = $this->screenDataProvider->getPalletServices();
        // What a parcel typed in starts as.
        $data['default_pack_type'] = PackTypeEnum::Standard->value;
        $data['carriers_error'] = $this->shipmentOptionsProvider->getCarriersError();
        $data['description_max_length'] = RemoteOrder::DESCRIPTION_MAX_LENGTH;
        $data['map'] = $this->adminMapConfigProvider->getMapConfig(true);
        // The pickup point as Polkurier has it now: its name and address today, or, when it's gone
        // or closed since the order was placed, a problem to fix before placing.
        $point = $this->screenDataProvider->describeRecipientPoint(
            $orderId,
            (string)($data[ShipmentDraftInterface::FIELD_CARRIER_CODE] ?? ''),
            (string)($data[ShipmentDraftInterface::FIELD_RECIPIENT_POINT_ID] ?? ''),
        );
        $data['recipient_point_closed'] = $point['closed'] ? $point['id'] : '';
        // Points chosen on the map are added to them.
        $data['point_details'] = (object)$this->screenDataProvider->describePoints(
            $point,
            (string)($data[ShipmentDraftInterface::FIELD_SENDER_POINT_ID] ?? ''),
        );
        $data['senders'] = (object)$this->getSenders();
        $data['store_sender'] = $this->describeStoreSender($storeId);
        // Only those that can be printed as the sender.
        $data['cover_addresses'] = $this->shipmentOptionsProvider->getSenderOptions(true);
        $data['templates'] = $this->screenDataProvider->getTemplates();
        // For automatic packing, from the website's Parcels settings: the type a shipment is until
        // it has parcels, as a new one starts ({@see \Polkurier\Shipping\Model\Shipment\Draft\DefaultDraftBuilder}).
        $data['default_shipment_type'] = $this->configProvider->getDefaultShipmentType($storeId)->value;
        $data['parcel_weight'] = $this->configProvider->getParcelWeight($storeId)->value;
        $data['carrier_notices'] = $this->carrierNoticeProvider->getNotices();
        $data['choices'] = $this->getChoices();
        // An address abroad is shown with its country.
        $data['home_country'] = Polkurier::HOME_COUNTRY;
        $data['urls'] = [
            'calculate' => $this->url->getUrl('polkurier/shipment/calculate'),
            'pickup_times' => $this->url->getUrl('polkurier/shipment/pickupTimes'),
            'collective_pickup' => $this->url->getUrl('polkurier/shipment/collectivePickup'),
            'pack' => $this->url->getUrl('polkurier/shipment/pack'),
            'pack_templates' => $this->url->getUrl('polkurier/packtemplate/index'),
            'addresses' => $this->url->getUrl('polkurier/address/index'),
        ];

        return [$orderId => $data];
    }

    /**
     * For the item list: every item the order has left to ship, with the quantity the draft ships
     * (0 when the draft leaves the item out; everything left when there's no draft). For a
     * Polkurier order going on an existing Magento shipment, that shipment's items instead, in the
     * quantities it carries.
     *
     * @param array<int, mixed> $draftItems rows of order_item_id and qty
     * @param array<int, float>|null $carried what the Magento shipment carries, by order item id
     *
     * @return array<int, array<string, mixed>>
     */
    private function getItems(Order $order, array $draftItems, ?array $carried = null): array
    {
        if ($carried !== null) {
            $draftItems = \array_map(
                static fn(int $id, float $qty): array => [
                    ItemInterface::FIELD_ORDER_ITEM_ID => $id,
                    ItemInterface::FIELD_QTY => $qty,
                ],
                \array_keys($carried),
                $carried,
            );
        }
        $chosen = [];
        foreach ($draftItems as $row) {
            if (\is_array($row) && isset($row[ItemInterface::FIELD_ORDER_ITEM_ID])) {
                $chosen[(int)$row[ItemInterface::FIELD_ORDER_ITEM_ID]]
                    = (float)\str_replace(',', '.', (string)($row[ItemInterface::FIELD_QTY] ?? 0));
            }
        }
        $items = [];
        foreach ($this->orderAmountCalculator->getItems($order) as $id => $item) {
            if ($carried !== null ? !isset($carried[$id]) : $item['qty_to_ship'] <= 0) {
                continue;
            }
            $items[] = [
                ItemInterface::FIELD_ORDER_ITEM_ID => $id,
                ItemInterface::FIELD_QTY => $chosen === [] ? $item['qty_to_ship'] : ($chosen[$id] ?? 0.0),
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
     * The Magento shipment a Polkurier order goes on: the one chosen or, when the order has nothing
     * left to ship, the latest without a Polkurier order. Comes with the cancelled Polkurier order
     * it replaces, what the shipment carries, and whether its customer can still be e-mailed about
     * it; null when there is none.
     *
     * @return array{
     *     increment_id: string,
     *     replaces: string|null,
     *     shipment_value: float,
     *     worth: array{products: float, order_total: float, whole: bool, value: float},
     *     qtys: array<int, float>,
     *     email_open: bool,
     * }|null
     */
    private function getReplaced(Order $order, ?int $targetId): ?array
    {
        $free = $this->nativeShipmentService->getFreeShipments($order);
        $id = $targetId ?? ($free === [] ? null : \max(\array_keys($free)));
        if ($id === null || !isset($free[$id])) {
            return null;
        }
        $qtys = $this->nativeShipmentService->getShipmentQuantities($order, $id);

        $worth = $this->orderAmountCalculator->getShipmentWorth($order, $qtys);
        $magentoShipment = $order->getShipmentsCollection()->getItemById($id);

        return $free[$id] + [
            'shipment_value' => $worth['value'],
            'worth' => $worth,
            'qtys' => $qtys,
            'email_open' => $magentoShipment instanceof MagentoShipment
                && NativeShipmentService::isEmailOpen($magentoShipment),
        ];
    }

    /**
     * How much cash on delivery is left to collect, when the order ships in parts.
     */
    private function getCodNotice(Order $order): string
    {
        if (!$this->orderAmountCalculator->isPriced($order)) {
            return $this->getCurrencyNotice($order);
        }
        $collected = $this->orderAmountCalculator->getCodCollected($order);
        if ($collected <= 0) {
            return (string)__('0 or empty: no cash on delivery.');
        }

        return (string)__(
            '0 or empty: no cash on delivery. Order total: '
            . '%1, of which the order\'s other Polkurier orders collect %2.',
            $this->priceCurrency->format(
                $this->orderAmountCalculator->getGrandTotal($order),
                false,
                2,
                null,
                OrderAmountCalculator::CURRENCY,
            ),
            $this->priceCurrency->format($collected, false, 2, null, OrderAmountCalculator::CURRENCY),
        );
    }

    /**
     * Why an amount isn't filled in: the order is in a currency that can't be given in złoty.
     */
    private function getCurrencyNotice(Order $order): string
    {
        return (string)__(
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
        foreach ($this->addressRepository->getSenderAddresses() as $address) {
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
        $address = $this->senderMapper->mapFromStore($storeId);

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
            'street' => $this->formatStreet(
                $address->getStreet(),
                $address->getHouseNumber(),
                $address->getFlatNumber(),
            ),
            'city' => \trim($address->getPostcode() . ' ' . $address->getCity()),
            'postcode' => $address->getPostcode(),
            'country' => $address->getCountry(),
            'phone' => $address->getPhone(),
            'email' => $address->getEmail(),
            'default' => $address->isDefault() ? '1' : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function describeMessage(Address $address): array
    {
        return [
            'label' => (string)__('The store\'s address'),
            'company' => (string)$address->getCompany(),
            'postcode' => $address->getPostcode(),
            'person' => $address->getPerson() === $address->getCompany() ? '' : $address->getPerson(),
            'street' => $this->formatStreet(
                $address->getStreet(),
                (string)$address->getHouseNumber(),
                $address->getFlatNumber(),
            ),
            'city' => \trim($address->getPostcode() . ' ' . $address->getCity()),
            'country' => $address->getCountry(),
            'phone' => $address->getPhone(),
            'email' => $address->getEmail(),
            'default' => '',
        ];
    }

    private function formatStreet(string $street, string $houseNumber, ?string $flatNumber): string
    {
        return \trim($street . ' ' . $houseNumber . ($flatNumber ? '/' . $flatNumber : ''));
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
            ShipmentDraftInterface::FIELD_CARRIER_CODE => $labels($this->shipmentOptionsProvider->getCarrierOptions()),
            ShipmentDraftInterface::FIELD_SHIPMENT_TYPE => (object)$this->shipmentTypes->toArray(),
            PackInterface::FIELD_PACK_TYPE => (object)$this->packTypes->toArray(),
            // The shipment type each parcel type makes.
            'pack_shipment_type' => (object)ParcelTypeMapper::getShipmentTypeMap(),
            ShipmentDraftInterface::FIELD_COD_TYPE => (object)$this->codTypes->toArray(),
            ShipmentDraftInterface::FIELD_COD_RETURN_TYPE => (object)$this->codReturnTypes->toArray(),
            ShipmentDraftInterface::FIELD_COD_BANK_ACCOUNT_ID => $labels(
                $this->shipmentOptionsProvider->getBankAccountChoices(),
            ),
            ShipmentDraftInterface::FIELD_SERVICES => (object)$this->services->toArray(),
            ShipmentDraftInterface::FIELD_PICKUP_MODE => (object)$this->pickupModes->toArray(),
        ];
    }

    /**
     * Sets a config value of a field in one of the form's modals, whose paths follow the form:
     * <fieldset>_modal, <fieldset>, <field>.
     *
     * @param array<string, mixed> $meta
     */
    private static function setFieldConfig(
        array &$meta,
        string $fieldset,
        string $field,
        string $key,
        mixed $value,
    ): void {
        $meta[$fieldset . '_modal']['children'][$fieldset]['children'][$field]['arguments']['data']['config'][$key]
            = $value;
    }
}
