<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Screen;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\OrderPointRepositoryInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Enum\ParcelWeightEnum;
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
        private ShipmentManagementInterface $management,
        private DraftHydrator $hydrator,
        private PackTemplateRepositoryInterface $packTemplateRepository,
        private AddressRepositoryInterface $addressRepository,
        private PackType $packTypes,
        private MapTokenProvider $mapTokenProvider,
        private ConfigProvider $configProvider,
        private OrderAmountCalculator $amountCalculator,
        private CarrierCatalog $catalog,
        private CarrierFeatureResolver $carrierFeatureResolver,
        private DefaultDraftBuilder $draftBuilder,
        private OrderPointRepositoryInterface $orderPointRepository,
    ) {}

    /**
     * The order's pickup point as Polkurier has it now: its address today, or the one the
     * customer chose it by when it can't be looked up, and whether it's closed or gone since,
     * a problem to fix before placing.
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
            $carrier = $this->catalog->getCarrier($carrierCode);
            $providers = $carrier !== null ? $this->carrierFeatureResolver->getPointProviders($carrier) : [];
            if ($providers === []) {
                return null;
            }
            $point = $this->catalog->findPoint($pointId, $providers);
        } catch (PolkurierException) {
            return null;
        }

        return $point !== null && $point->isAvailable() ? $point : false;
    }

    /**
     * The draft a new shipment of the order starts from, as the screens take it; empty when the
     * order can't be shipped with Polkurier.
     *
     * @param array<int, float>|null $qtys what a Magento shipment there is carries, by order item
     *     id, for a Polkurier order going on it; null for what the order has left
     *
     * @return array<string, mixed>
     */
    public function getDefaultValues(Order $order, ?array $qtys = null): array
    {
        try {
            return $this->hydrator->toArray($qtys !== null
                ? $this->draftBuilder->createFromOrder($order, $qtys)
                : $this->management->getDefaultDraft((int)$order->getEntityId()));
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
     * Where the pickup point map is, its token and when that runs out (empty and 0 when it can't
     * be had). Where the screen asks for a fresh one is the view's to add
     * (the polkurier/shipment/mapToken action).
     *
     * @return array{token: string, expires: int, baseUrl: string}
     */
    public function getMap(): array
    {
        try {
            $token = $this->mapTokenProvider->getToken();
            $expires = $this->mapTokenProvider->getExpiry();
        } catch (PolkurierException) {
            $token = '';
            $expires = 0;
        }

        return [
            'token' => $token,
            'expires' => $expires,
            'baseUrl' => $this->configProvider->getMapUrl() . '/',
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
            'grand_total' => $this->amountCalculator->getGrandTotal($order),
            'shipping_charged' => $this->amountCalculator->getShippingCharged($order),
            'cod_collected' => $this->amountCalculator->getCodCollected($order),
            'cod_remaining' => $this->amountCalculator->getCodRemaining($order),
            'shipping_covered' => $this->amountCalculator->isShippingCovered($order),
        ];
    }
}
