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
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;
use Polkurier\Shipping\Model\Shipment\Draft\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
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
        private CarrierCatalog $carrierCatalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private DefaultDraftBuilder $defaultDraftBuilder,
        private OrderPointRepositoryInterface $orderPointRepository,
        private UrlInterface $url,
    ) {}

    /**
     * The order's pickup point as Polkurier has it now: its current address or, when it can't be
     * looked up, the address the customer chose it by. Also whether it has closed or gone since,
     * which must be fixed before placing.
     *
     * @return array{id: string, label: string, closed: bool}
     */
    public function describeRecipientPoint(int $orderId, string $carrierCode, string $pointId): array
    {
        $point = $this->findRecipientPoint($carrierCode, $pointId);
        if ($point) {
            $label = (string)$point->getAddress();
        } else {
            $chosen = $pointId === '' ? null : $this->orderPointRepository->getForOrder($orderId);
            $label = $chosen !== null && $chosen->getPointId() === $pointId ? (string)$chosen->getPointLabel() : '';
        }

        return ['id' => $pointId, 'label' => $label, 'closed' => $point === false];
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
        if ($carrierCode === '' || $pointId === '') {
            return null;
        }
        try {
            $carrier = $this->carrierCatalog->getCarrier($carrierCode);
            $providers = $carrier !== null ? $this->carrierFeatureResolver->getPointProviders($carrier) : [];
            if ($providers === []) {
                return null;
            }
            $point = $this->carrierCatalog->findPoint($pointId, $providers);
        } catch (PolkurierException) {
            return null;
        }

        return $point !== null && $point->isAvailable() ? $point : false;
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
            return $this->draftHydrator->toArray($this->defaultDraftBuilder->createFromOrder($order, $qtys));
        } catch (LocalizedException) {
            return [];
        }
    }

    /**
     * Where each sender address is and the point it hands parcels over at, for the drop-off
     * point's map and default.
     *
     * @return array<string, array{postcode: string, point_id: string}> by address id
     */
    public function getSenderPoints(): array
    {
        $points = [];
        foreach ($this->addressRepository->getSenderAddresses() as $address) {
            $points[(string)$address->getId()] = [
                'postcode' => $address->getPostcode(),
                'point_id' => (string)$address->getPointId(),
            ];
        }

        return $points;
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
