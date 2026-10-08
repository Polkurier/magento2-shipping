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
use Polkurier\Shipping\Enum\Config\ExternalOrderIdEnum;
use Polkurier\Shipping\Model\Config\ConfigProvider;

/**
 * The number that, with the installation's application id
 * ({@see \Polkurier\Shipping\Model\Sdk\ApplicationIdProvider}), identifies a Polkurier order as the
 * shop's. As configured, it's the number of the Magento shipment the order goes with, or of the
 * Magento order.
 *
 * The Magento shipment a new Polkurier order makes doesn't exist while the order is placed, so its
 * number is taken from Magento's numbering beforehand and given to it once it's created
 * ({@see \Polkurier\Shipping\Model\Shipment\Native\ReservedNumberRegistry}). An attempt sent again
 * keeps the number it took. A refused attempt keeps nothing, and leaves a gap in the shipment
 * numbers.
 */
readonly class ExternalIdService
{
    public function __construct(
        private ConfigProvider $configProvider,
        private MagentoShipmentRepository $magentoShipmentRepository,
        private SequenceManager $sequenceManager,
    ) {}

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
                    'id' => (string)$this->magentoShipmentRepository->get($magentoShipmentId)->getIncrementId(),
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
