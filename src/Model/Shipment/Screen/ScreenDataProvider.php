<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Screen;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\PickupPoint\DefaultSenderPointManager;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Shipping\Model\PickupPoint\PointFinder;
use Polkurier\Shipping\Model\Shipment\Draft\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Webservice\Response\CourierPoint;

/**
 * What both shipment screens start from: the Polkurier shipment screen and the shipping label
 * options on Magento's New Shipment page. Built in one place so the two don't drift apart.
 */
readonly class ScreenDataProvider
{
    public function __construct(
        private DraftHydrator $draftHydrator,
        private PackTemplateRepositoryInterface $packTemplateRepository,
        private AddressRepositoryInterface $addressRepository,
        private PackType $packTypes,
        private MapTokenProvider $mapTokenProvider,
        private ConfigProvider $configProvider,
        private OrderAmountCalculator $orderAmountCalculator,
        private PointFinder $pointFinder,
        private DefaultDraftBuilder $defaultDraftBuilder,
        private OrderPointRepositoryInterface $orderPointRepository,
        private UrlInterface $url,
        private DefaultSenderPointManager $defaultSenderPointManager,
    ) {}

    /**
     * The order's pickup point as Polkurier has it now: its name and current address or, when it
     * can't be looked up, the address the customer chose it by. Also whether it has closed or gone
     * since, which must be fixed before placing.
     *
     * @return array{id: string, name: string, label: string, closed: bool}
     */
    public function describeRecipientPoint(int $orderId, string $carrierCode, string $pointId): array
    {
        $point = $this->findRecipientPoint($carrierCode, $pointId);
        if ($point) {
            return ['id' => $pointId, ...$this->pointFinder->describe($point), 'closed' => false];
        }
        $chosen = $pointId === '' ? null : $this->orderPointRepository->getForOrder($orderId);
        $label = $chosen !== null && $chosen->getPointId() === $pointId ? (string)$chosen->getPointLabel() : '';

        return ['id' => $pointId, 'name' => '', 'label' => $label, 'closed' => $point === false];
    }

    /**
     * The names and addresses of the points a screen starts with, by code: the pickup point as
     * {@see describeRecipientPoint()} has it, the drop-off point, and the account's drop-off points
     * of the other carriers, which choosing a carrier brings in. Points that can't be looked up are
     * left out.
     *
     * @param array{id: string, name: string, label: string} $recipientPoint
     *
     * @return array<string, array{name: string, label: string}>
     */
    public function describePoints(array $recipientPoint, string $senderPointId): array
    {
        $ids = [$senderPointId, ...\array_values($this->defaultSenderPointManager->get())];
        $points = [];
        foreach (\array_unique(\array_filter($ids, static fn(string $id): bool => $id !== '')) as $id) {
            $point = $this->pointFinder->find($id);
            if ($point) {
                $points[$id] = $this->pointFinder->describe($point);
            }
        }
        if ($recipientPoint['id'] !== '' && ($recipientPoint['name'] !== '' || $recipientPoint['label'] !== '')) {
            $points[$recipientPoint['id']] = ['name' => $recipientPoint['name'], 'label' => $recipientPoint['label']];
        }

        return $points;
    }

    /**
     * The additional services only pallets may have.
     *
     * @return string[]
     */
    public function getPalletServices(): array
    {
        return \array_map(static fn($service): string => $service->value, CarrierFeatureResolver::PALLET_SERVICES);
    }

    /**
     * The pickup point a shipment is to go to, as Polkurier has it now: the point while it's open,
     * false when Polkurier no longer has it or it's closed, null when that can't be told (no such
     * carrier, or Polkurier can't be asked), which is never taken as closed.
     */
    public function findRecipientPoint(string $carrierCode, string $pointId): CourierPoint|false|null
    {
        $point = $this->pointFinder->find($pointId, $carrierCode);

        return $point && !$point->isAvailable() ? false : $point;
    }

    /**
     * The draft a new shipment of the Polkurier order starts from, as the screens take it; empty
     * when it can't be put together.
     *
     * @param array<int, float>|null $qtys what an existing Magento shipment carries, by order item id,
     *     for a Polkurier order going on it; null for what the order has left
     *
     * @return array<string, mixed>
     */
    public function getDefaultValues(Order $order, ?array $qtys): array
    {
        try {
            return $this->draftHydrator->convertToArray($this->defaultDraftBuilder->createFromOrder($order, $qtys));
        } catch (LocalizedException) {
            return [];
        }
    }

    /**
     * Each sender address's postcode, where the drop-off point's map starts.
     *
     * @return array<string, string> by address id
     */
    public function getSenderPostcodes(): array
    {
        $postcodes = [];
        foreach ($this->addressRepository->getSenderAddresses() as $address) {
            $postcodes[(string)$address->getId()] = $address->getPostcode();
        }

        return $postcodes;
    }

    /**
     * The pack templates, for filling in a parcel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTemplates(): array
    {
        $packTypes = $this->packTypes->toArray();
        $templates = [];
        foreach ($this->packTemplateRepository->getAvailable() as $template) {
            $templates[] = [
                'id' => (string)$template->getId(),
                'name' => $template->getName(),
                'shipment_type' => $template->getShipmentType(),
                'pack_type' => $template->getPackType(),
                'pack_type_label' => (string)($packTypes[$template->getPackType()] ?? $template->getPackType()),
                'length' => $template->getLength(),
                'width' => $template->getWidth(),
                'height' => $template->getHeight(),
                'weight' => $template->getWeight(),
                // What the packaging weighs, added to what a parcel holds (rules.parcelWeight).
                'tare' => $template->getTare(),
                'is_default' => $template->isDefault(),
            ];
        }

        return $templates;
    }

    /**
     * Where the pickup point map is, its token and when that runs out (empty and 0 when it can't be
     * obtained), and where the screen asks for a fresh one.
     *
     * @return array{token: string, expires: int, baseUrl: string, tokenUrl: string}
     */
    public function getMap(): array
    {
        return $this->mapTokenProvider->getTokenWithExpiry() + [
            'baseUrl' => $this->configProvider->getMapUrl() . '/',
            'tokenUrl' => $this->url->getUrl('polkurier/shipment/mapToken'),
        ];
    }

    /**
     * The order's amounts in złoty, as cash on delivery and insurance start from them.
     *
     * @return array{grand_total: float, shipping_charged: float, cod_collected: float, cod_remaining: float, shipping_covered: bool}
     */
    public function getAmounts(Order $order): array
    {
        return [
            'grand_total' => $this->orderAmountCalculator->getGrandTotal($order),
            'shipping_charged' => $this->orderAmountCalculator->getShippingCharged($order),
            'cod_collected' => $this->orderAmountCalculator->getCodCollected($order),
            'cod_remaining' => $this->orderAmountCalculator->getCodRemaining($order),
            'shipping_covered' => $this->orderAmountCalculator->isShippingCovered($order),
        ];
    }
}
