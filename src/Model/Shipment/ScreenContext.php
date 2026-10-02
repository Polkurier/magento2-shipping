<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\CourierPoint;

/**
 * What both shipment screens start from: the Polkurier shipment screen and the shipping label
 * options on Magento's New Shipment page. Built in one place so the two don't drift apart.
 */
class ScreenContext
{
    public function __construct(
        private readonly ShipmentManagementInterface $management,
        private readonly DraftHydrator $hydrator,
        private readonly MasterData $masterData,
        private readonly PackType $packTypes,
        private readonly MapTokenProvider $mapTokenProvider,
        private readonly Config $config,
        private readonly OrderAmounts $amounts,
        private readonly UrlInterface $url,
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        private readonly DefaultDraftBuilder $draftBuilder,
    ) {}

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
            $providers = $carrier !== null ? $this->features->getPointProviders($carrier) : [];
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
     * What a parcel of a pack template weighs, as the screens weigh it (ParcelWeightEnum).
     */
    public function getParcelWeight(int $storeId): string
    {
        return $this->config->getParcelWeight($storeId)->value;
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
        foreach ($this->masterData->getPackTemplates() as $template) {
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
                'is_default' => $template->isDefault(),
            ];
        }

        return $templates;
    }

    /**
     * Where the pickup point map is, its token and when that runs out (empty and 0 when it can't
     * be had), and where the screen asks for a fresh one when it opens the map.
     *
     * @return array{token: string, expires: int, tokenUrl: string, baseUrl: string}
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
            'tokenUrl' => $this->url->getUrl('polkurier/shipment/mapToken'),
            'baseUrl' => $this->config->getMapUrl() . '/',
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
            'grand_total' => $this->amounts->getGrandTotal($order),
            'shipping_charged' => $this->amounts->getShippingCharged($order),
            'cod_collected' => $this->amounts->getCodCollected($order),
            'cod_remaining' => $this->amounts->getCodRemaining($order),
            'shipping_covered' => $this->amounts->isShippingCovered($order),
        ];
    }
}
