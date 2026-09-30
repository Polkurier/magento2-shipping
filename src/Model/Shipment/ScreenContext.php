<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

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
    ) {}

    /**
     * The draft a new shipment of the order starts from, as the screens take it; empty when the
     * order can't be shipped with Polkurier.
     *
     * @return array<string, mixed>
     */
    public function getDefaultValues(Order $order): array
    {
        try {
            return $this->hydrator->toArray($this->management->getDefaultDraft((int)$order->getEntityId()));
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
     * Where the pickup point map is, and its token; an empty token when it can't be had, which
     * leaves the map closed.
     *
     * @return array{token: string, baseUrl: string}
     */
    public function getMap(): array
    {
        try {
            $token = $this->mapTokenProvider->getToken();
        } catch (PolkurierException) {
            $token = '';
        }

        return ['token' => $token, 'baseUrl' => $this->config->getMapUrl() . '/'];
    }

    /**
     * The order's amounts in złoty, as cash on delivery and insurance start from them.
     *
     * @return array{grand_total: float, cod_collected: float, cod_remaining: float}
     */
    public function getAmounts(Order $order): array
    {
        return [
            'grand_total' => $this->amounts->getGrandTotal($order),
            'cod_collected' => $this->amounts->getCodCollected($order),
            'cod_remaining' => $this->amounts->getCodRemaining($order),
        ];
    }
}
