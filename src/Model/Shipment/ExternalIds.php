<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Model\Order;
use Magento\SalesSequence\Model\Manager as SequenceManager;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\ApplicationId;

/**
 * What a Polkurier order is identified by as the shop's: the installation's application id and,
 * as configured, the number of the Magento shipment it goes with or of the Magento order.
 *
 * The Magento shipment a new Polkurier order makes doesn't exist while the order is placed, so its
 * number is taken from Magento's numbering beforehand and given to it once it's created
 * ({@see ReservedShipmentNumbers}). An attempt sent again keeps the number it took. A refused
 * attempt keeps nothing, and leaves a gap in the shipment numbers.
 */
readonly class ExternalIds
{
    public function __construct(
        private Config $config,
        private ApplicationId $applicationId,
        private MagentoShipmentRepository $magentoShipments,
        private SequenceManager $sequenceManager,
    ) {}

    public function getApplicationId(): string
    {
        return $this->applicationId->get();
    }

    /**
     * @param ShipmentInterface|null $attempt the order's earlier attempt that is sent again, if any
     * @param int|null $magentoShipmentId the existing Magento shipment it goes on, if any
     * @return array{id: string, reserved: string|null} what is sent, and the number taken for a
     *         Magento shipment still to be created
     */
    public function forAttempt(Order $order, ?ShipmentInterface $attempt, ?int $magentoShipmentId): array
    {
        if ($this->config->getExternalOrderId() === ExternalOrderIdEnum::Order) {
            return ['id' => (string)$order->getIncrementId(), 'reserved' => null];
        }
        if ($magentoShipmentId !== null) {
            return ['id' => (string)$this->magentoShipments->get($magentoShipmentId)->getIncrementId(), 'reserved' => null];
        }
        $reserved = $attempt?->getReservedShipmentNumber()
            ?? (string)$this->sequenceManager->getSequence('shipment', (int)$order->getStoreId())->getNextValue();

        return ['id' => $reserved, 'reserved' => $reserved];
    }
}
