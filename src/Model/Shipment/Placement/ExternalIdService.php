<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Model\Order;
use Magento\SalesSequence\Model\Manager as SequenceManager;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Enum\ExternalOrderIdEnum;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;
use Polkurier\Shipping\Model\Shipment\Native\ReservedNumberRegistry;

/**
 * What a Polkurier order is identified by as the shop's: the installation's application id and,
 * as configured, the number of the Magento shipment it goes with or of the Magento order.
 *
 * The Magento shipment a new Polkurier order makes doesn't exist while the order is placed, so its
 * number is taken from Magento's numbering beforehand and given to it once it's created
 * ({@see ReservedNumberRegistry}). An attempt sent again keeps the number it took. A refused
 * attempt keeps nothing, and leaves a gap in the shipment numbers.
 */
readonly class ExternalIdService
{
    public function __construct(
        private ConfigProvider $configProvider,
        private ApplicationIdProvider $applicationIdProvider,
        private MagentoShipmentRepository $magentoShipments,
        private SequenceManager $sequenceManager,
    ) {}

    public function getApplicationId(): string
    {
        return $this->applicationIdProvider->get();
    }

    /**
     * @param ShipmentInterface|null $attempt the order's earlier attempt that is sent again, if any
     * @param int|null $magentoShipmentId the existing Magento shipment it goes on, if any
     *
     * @return array{id: string, reserved: string|null} what is sent, and the number taken for a
     *         Magento shipment still to be created
     * @throws LocalizedException when the Magento shipment it goes on is gone
     */
    public function forAttempt(Order $order, ?ShipmentInterface $attempt, ?int $magentoShipmentId): array
    {
        if ($this->configProvider->getExternalOrderId() === ExternalOrderIdEnum::Order) {
            return ['id' => (string)$order->getIncrementId(), 'reserved' => null];
        }
        if ($magentoShipmentId !== null) {
            try {
                return [
                    'id' => (string)$this->magentoShipments->get($magentoShipmentId)->getIncrementId(),
                    'reserved' => null,
                ];
            } catch (NoSuchEntityException $e) {
                throw new LocalizedException(__('The shipment to create a label for is missing.'), $e);
            }
        }
        $reserved = $attempt?->getReservedShipmentNumber()
            ?? (string)$this->sequenceManager->getSequence('shipment', (int)$order->getStoreId())->getNextValue();

        return ['id' => $reserved, 'reserved' => $reserved];
    }
}
